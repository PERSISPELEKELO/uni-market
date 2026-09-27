"""Trains the dispute-category classifier and saves it for app.py to load.

Usage: python train.py

Reads data/disputes.csv (see data/generate_dataset.py for how that file
was produced - it is synthetic, templated data, not real disputes),
splits it into a train/test set, fits a TF-IDF + Logistic Regression
pipeline, prints accuracy and a per-category precision/recall/F1 report
on the held-out test split, saves a confusion matrix image, and saves the
trained pipeline with joblib for app.py to load at startup.
"""

from __future__ import annotations

import csv
from pathlib import Path

import joblib
import matplotlib

matplotlib.use("Agg")  # no display available when this runs headless / in CI

import matplotlib.pyplot as plt
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import ConfusionMatrixDisplay, accuracy_score, classification_report, confusion_matrix
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline

DATA_PATH = Path(__file__).parent / "data" / "disputes.csv"
MODEL_PATH = Path(__file__).parent / "models" / "model.joblib"
CONFUSION_MATRIX_PATH = Path(__file__).parent / "models" / "confusion_matrix.png"

RANDOM_STATE = 42


def load_dataset() -> tuple[list[str], list[str]]:
    texts: list[str] = []
    labels: list[str] = []

    with DATA_PATH.open(newline="", encoding="utf-8") as handle:
        reader = csv.DictReader(handle)
        for row in reader:
            texts.append(row["text"])
            labels.append(row["category"])

    return texts, labels


def main() -> None:
    texts, labels = load_dataset()
    print(f"Loaded {len(texts)} rows from {DATA_PATH}")

    x_train, x_test, y_train, y_test = train_test_split(
        texts,
        labels,
        test_size=0.2,
        random_state=RANDOM_STATE,
        stratify=labels,
    )

    pipeline = Pipeline(
        [
            ("tfidf", TfidfVectorizer(lowercase=True, stop_words="english", ngram_range=(1, 2))),
            ("classifier", LogisticRegression(max_iter=1000, random_state=RANDOM_STATE)),
        ]
    )

    pipeline.fit(x_train, y_train)

    predictions = pipeline.predict(x_test)

    accuracy = accuracy_score(y_test, predictions)
    print(f"\nAccuracy on held-out test split: {accuracy:.3f}\n")

    print("Per-category precision / recall / F1:")
    print(classification_report(y_test, predictions))

    labels_sorted = sorted(set(labels))
    matrix = confusion_matrix(y_test, predictions, labels=labels_sorted)

    display = ConfusionMatrixDisplay(confusion_matrix=matrix, display_labels=labels_sorted)
    figure, axis = plt.subplots(figsize=(6, 6))
    display.plot(ax=axis, xticks_rotation=45, colorbar=False)
    figure.tight_layout()

    CONFUSION_MATRIX_PATH.parent.mkdir(parents=True, exist_ok=True)
    figure.savefig(CONFUSION_MATRIX_PATH)
    print(f"Saved confusion matrix to {CONFUSION_MATRIX_PATH}")

    MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(pipeline, MODEL_PATH)
    print(f"Saved trained model to {MODEL_PATH}")


if __name__ == "__main__":
    main()
