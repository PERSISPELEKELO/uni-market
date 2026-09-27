"""Tests for the Flask endpoint, using Flask's own test client - no live
server or real model file required, since app.get_model()/get_analyzer()
are stubbed out below the same way Laravel's own tests fake the HTTP call
instead of hitting a real service.
"""

from __future__ import annotations

import sys
from pathlib import Path
from unittest.mock import MagicMock

import numpy as np
import pytest

sys.path.insert(0, str(Path(__file__).parent.parent))

import app as app_module  # noqa: E402


class FakeModel:
    """Always predicts 'delivery_issue' with 0.9 confidence.

    predict_proba() returns a numpy array here, matching what a real
    scikit-learn model actually returns - app.py calls .argmax() on the
    result, which a plain list does not support.
    """

    classes_ = np.array(["delivery_issue", "misdescribed_item", "other", "payment_issue"])

    def predict_proba(self, texts: list[str]):
        return np.array([[0.9, 0.05, 0.03, 0.02]])


@pytest.fixture(autouse=True)
def stub_model_and_analyzer(monkeypatch):
    monkeypatch.setattr(app_module, "_model", FakeModel())
    monkeypatch.setattr(app_module, "get_model", lambda: FakeModel())

    analyzer = MagicMock()
    analyzer.polarity_scores.return_value = {"compound": -0.5}
    monkeypatch.setattr(app_module, "_analyzer", analyzer)
    monkeypatch.setattr(app_module, "get_analyzer", lambda: analyzer)

    yield


@pytest.fixture
def client():
    app_module.app.config.update(TESTING=True)
    return app_module.app.test_client()


def test_health_endpoint(client):
    response = client.get("/health")

    assert response.status_code == 200
    assert response.get_json() == {"status": "ok"}


def test_valid_input_returns_the_full_contract(client):
    response = client.post(
        "/api/analyze-dispute",
        json={"transaction_id": 42, "dispute_reason": "The seller never showed up for the handover."},
    )

    assert response.status_code == 200
    body = response.get_json()

    assert -1 <= body["sentiment_score"] <= 1
    assert 0 <= body["confidence_score"] <= 1
    assert body["suggested_resolution"] == "REFUND"
    assert "delivery issue" in body["summary"]
    assert set(body.keys()) == {"sentiment_score", "confidence_score", "suggested_resolution", "summary"}


def test_low_confidence_appends_a_review_note(client, monkeypatch):
    class LowConfidenceModel(FakeModel):
        def predict_proba(self, texts: list[str]):
            return np.array([[0.3, 0.3, 0.2, 0.2]])

    monkeypatch.setattr(app_module, "get_model", lambda: LowConfidenceModel())

    response = client.post(
        "/api/analyze-dispute",
        json={"transaction_id": 1, "dispute_reason": "Something about this transaction was not right."},
    )

    assert response.status_code == 200
    assert "recommend careful human review" in response.get_json()["summary"]


@pytest.mark.parametrize(
    "payload",
    [
        {"transaction_id": 1, "dispute_reason": ""},
        {"transaction_id": 1, "dispute_reason": "   "},
    ],
)
def test_empty_text_returns_400(client, payload):
    response = client.post("/api/analyze-dispute", json=payload)

    assert response.status_code == 400
    assert "error" in response.get_json()


@pytest.mark.parametrize(
    "payload",
    [
        {"transaction_id": 1},
        {"transaction_id": 1, "dispute_reason": 12345},
        {"transaction_id": 1, "dispute_reason": ["not", "a", "string"]},
        "not even a json object",
    ],
)
def test_malformed_input_returns_400_instead_of_crashing(client, payload):
    response = client.post("/api/analyze-dispute", json=payload)

    assert response.status_code == 400
    assert "error" in response.get_json()


def test_no_json_body_at_all_returns_400(client):
    response = client.post("/api/analyze-dispute", data="not json", content_type="text/plain")

    assert response.status_code == 400
