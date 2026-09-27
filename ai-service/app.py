"""Dispute-analysis microservice for UniMarket.

Serves POST /api/analyze-dispute for the Laravel app (see
app/Services/DisputeAnalysisService.php in the main repo for the exact
contract this implements) and GET /health for a liveness check.

The trained classifier and the VADER sentiment analyser are both loaded
once, at import time - not per request - so a request only ever pays for
inference, comfortably inside Laravel's 3-second HTTP timeout.
"""

from __future__ import annotations

import logging
import os
import time
from pathlib import Path
from typing import Any

import joblib
from flask import Flask, jsonify, request
from vaderSentiment.vaderSentiment import SentimentIntensityAnalyzer

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("ai-service")

MODEL_PATH = Path(__file__).parent / "models" / "model.joblib"
CONFIDENCE_THRESHOLD = float(os.environ.get("CONFIDENCE_THRESHOLD", "0.6"))

# category (the classifier's own label) -> the short code token Laravel stores
# verbatim in disputes.ai_suggested_resolution, and a human-readable phrase for
# the summary sentence.
RESOLUTION_MAP: dict[str, tuple[str, str]] = {
    "misdescribed_item": ("PARTIAL_REFUND_OR_RETURN", "a partial refund or return"),
    "delivery_issue": ("REFUND", "a refund"),
    "payment_issue": ("PAYMENT_VERIFICATION", "payment verification"),
    "other": ("MANUAL_REVIEW", "manual review"),
}

CATEGORY_LABELS: dict[str, str] = {
    "misdescribed_item": "a misdescribed item",
    "delivery_issue": "a delivery issue",
    "payment_issue": "a payment issue",
    "other": "an unclassified issue",
}

app = Flask(__name__)

_model = None
_analyzer: SentimentIntensityAnalyzer | None = None


def load_model():
    """Loads the trained pipeline from disk. Called once, eagerly, below -
    not lazily on the first request. A request landing right after the
    process starts still has to wait for this exact disk read if it isn't
    already done, and on a slow disk that read alone can take longer than
    Laravel's 3-second HTTP timeout, so the first "real" request must never
    be the one paying for it.
    """
    global _model

    if not MODEL_PATH.exists():
        logger.warning("No trained model at %s. Run `python train.py` first. "
                        "The service will start, but /api/analyze-dispute will return 503 until it exists.", MODEL_PATH)
        return

    logger.info("Loading model from %s", MODEL_PATH)
    _model = joblib.load(MODEL_PATH)
    logger.info("Model loaded.")


def get_model():
    return _model


def get_analyzer() -> SentimentIntensityAnalyzer:
    global _analyzer
    if _analyzer is None:
        _analyzer = SentimentIntensityAnalyzer()
    return _analyzer


# Both loaded eagerly, at import time - i.e. before app.run() below ever
# starts accepting connections - rather than lazily on the first request.
load_model()
get_analyzer()


def clamp(value: float, low: float, high: float) -> float:
    return max(low, min(high, value))


def build_summary(category: str, confidence: float, sentiment: float, resolution_phrase: str) -> str:
    tone = "negative" if sentiment < -0.05 else "positive" if sentiment > 0.05 else "neutral"
    category_label = CATEGORY_LABELS.get(category, "an unclassified issue")

    summary = (
        f"Classified as {category_label} (confidence {confidence:.2f}). "
        f"Buyer tone is {tone}. Suggested resolution: {resolution_phrase}."
    )

    if confidence < CONFIDENCE_THRESHOLD:
        summary += " Low confidence, recommend careful human review."

    return summary


@app.get("/health")
def health():
    return jsonify({"status": "ok"})


@app.post("/api/analyze-dispute")
def analyze_dispute():
    started_at = time.monotonic()

    payload: Any = request.get_json(silent=True)

    if not isinstance(payload, dict):
        return jsonify({"error": "Request body must be a JSON object."}), 400

    dispute_reason = payload.get("dispute_reason")

    if not isinstance(dispute_reason, str) or dispute_reason.strip() == "":
        return jsonify({"error": "\"dispute_reason\" is required and must be a non-empty string."}), 400

    text = dispute_reason.strip()

    model = get_model()

    if model is None:
        return jsonify({"error": "Model is not available. Run `python train.py` first."}), 503

    sentiment = clamp(get_analyzer().polarity_scores(text)["compound"], -1.0, 1.0)

    probabilities = model.predict_proba([text])[0]
    classes = model.classes_
    best_index = probabilities.argmax()
    category = classes[best_index]
    confidence = clamp(float(probabilities[best_index]), 0.0, 1.0)

    resolution_token, resolution_phrase = RESOLUTION_MAP.get(category, RESOLUTION_MAP["other"])
    summary = build_summary(category, confidence, sentiment, resolution_phrase)

    elapsed_ms = (time.monotonic() - started_at) * 1000
    logger.info(
        "transaction_id=%s category=%s confidence=%.2f sentiment=%.2f took=%.1fms",
        payload.get("transaction_id"),
        category,
        confidence,
        sentiment,
        elapsed_ms,
    )

    return jsonify(
        {
            "sentiment_score": round(sentiment, 2),
            "confidence_score": round(confidence, 2),
            "suggested_resolution": resolution_token,
            "summary": summary,
        }
    )


if __name__ == "__main__":
    port = int(os.environ.get("PORT", "8001"))
    app.run(host="127.0.0.1", port=port)
