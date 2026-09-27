"""Evaluates the already-trained model against real student dispute text.

Usage: python evaluate_real.py

This is a *separate* evaluation, not a training run: it loads
models/model.joblib exactly as app.py does and only ever calls .predict()
on it. data/real_test.csv is never used to fit or tune the model anywhere
in this project - see train.py, which only ever reads data/disputes.csv.

Expects data/real_test.csv with two columns: text, label. The category
names in "label" must match the ones the model was trained on
(misdescribed_item, delivery_issue, payment_issue, other) for the report
to be meaningful - anything else still runs, but classification_report
will show 0 support for categories that never appear and the model can
never predict a category it wasn't trained on.
"""

from __future__ import annotations

import csv
import sys
from pathlib import Path

import joblib
import matplotlib

matplotlib.use("Agg")  # no display available when this runs headless / in CI

import matplotlib.pyplot as plt
from sklearn.metrics import ConfusionMatrixDisplay, accuracy_score, classification_report, confusion_matrix

MODEL_PATH = Path(__file__).parent / "models" / "model.joblib"
REAL_DATA_PATH = Path(__file__).parent / "data" / "real_test.csv"
CONFUSION_MATRIX_PATH = Path(__file__).parent / "models" / "confusion_matrix_real.png"


def load_real_dataset() -> tuple[list[str], list[str]]:
    if not REAL_DATA_PATH.exists():
        print(
            f"No file at {REAL_DATA_PATH}.\n"
            "This script evaluates against real collected dispute text, not the synthetic "
            "training set - add a CSV there with \"text\" and \"label\" columns first.",
            file=sys.stderr,
        )
        sys.exit(1)

    texts: list[str] = []
    labels: list[str] = []

    with REAL_DATA_PATH.open(newline="", encoding="utf-8") as handle:
        reader = csv.DictReader(handle)

        if reader.fieldnames is None or {"text", "label"} - set(reader.fieldnames):
            print(
                f'{REAL_DATA_PATH} must have "text" and "label" columns, found: {reader.fieldnames}',
                file=sys.stderr,
            )
            sys.exit(1)

        for row in reader:
            text = (row["text"] or "").strip()
            label = (row["label"] or "").strip()

            if text == "" or label == "":
                continue

            texts.append(text)
            labels.append(label)

    if not texts:
        print(f"{REAL_DATA_PATH} has no usable rows (need non-empty text and label in each).", file=sys.stderr)
        sys.exit(1)

    return texts, labels


def main() -> None:
    if not MODEL_PATH.exists():
        print(f"No trained model at {MODEL_PATH}. Run `python train.py` first.", file=sys.stderr)
        sys.exit(1)

    model = joblib.load(MODEL_PATH)
    texts, labels = load_real_dataset()

    print(f"Loaded {len(texts)} real rows from {REAL_DATA_PATH}\n")

    predictions = model.predict(texts)

    accuracy = accuracy_score(labels, predictions)
    print(f"Accuracy on real student dispute text: {accuracy:.3f}\n")

    print("Per-category precision / recall / F1:")
    print(classification_report(labels, predictions, zero_division=0))

    labels_sorted = sorted(set(labels) | set(predictions))
    matrix = confusion_matrix(labels, predictions, labels=labels_sorted)

    display = ConfusionMatrixDisplay(confusion_matrix=matrix, display_labels=labels_sorted)
    figure, axis = plt.subplots(figsize=(6, 6))
    display.plot(ax=axis, xticks_rotation=45, colorbar=False)
    axis.set_title("Confusion matrix - real student dispute text")
    figure.tight_layout()

    CONFUSION_MATRIX_PATH.parent.mkdir(parents=True, exist_ok=True)
    figure.savefig(CONFUSION_MATRIX_PATH)
    print(f"Saved confusion matrix to {CONFUSION_MATRIX_PATH}")


if __name__ == "__main__":
    main()
