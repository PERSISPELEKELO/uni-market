"""Generates data/disputes.csv - a SYNTHETIC training set.

Every row is templated: a random combination of an item, an opening
phrase, a complaint detail and (sometimes) a closing phrase, drawn from
fixed phrase banks below. Nothing here is a real dispute or real student
data - it exists only to give the classifier in train.py something
structured to learn from. Re-running this script with the same seed
reproduces the exact same file.

Usage: python data/generate_dataset.py
"""

import csv
import random
from pathlib import Path

SEED = 42
ROWS_PER_CATEGORY = 55

ITEMS = [
    "the laptop", "the phone", "the textbook", "the calculator", "the backpack",
    "the desk lamp", "the mini fridge", "the bicycle", "the headphones", "the jacket",
    "the microwave", "the printer", "the mattress", "the kettle", "the shoes",
    "the monitor", "the speaker", "the chair", "the guitar", "the tablet",
]

MISDESCRIBED_OPENERS = [
    "The listing said {item} was in excellent condition, but it clearly is not.",
    "{item} arrived in much worse shape than the photos showed.",
    "I received {item} and it does not match the description at all.",
    "The seller described {item} as brand new, but it looks used.",
    "{item} is not the same model shown in the listing photos.",
    "I was told {item} had no issues, which is not true.",
]
MISDESCRIBED_DETAILS = [
    "There is a large crack that was never mentioned.",
    "Several parts are missing that were supposed to be included.",
    "It is scratched all over and clearly used before.",
    "The colour is completely different from the pictures.",
    "It does not turn on, even though the seller said it works fine.",
    "There are stains and a bad smell that were not disclosed.",
    "The size is wrong compared to what was advertised.",
    "It is a much older model than what was listed.",
]

DELIVERY_OPENERS = [
    "The seller never showed up for the handover we agreed on.",
    "I have been waiting for {item} for over a week with no update.",
    "We arranged to meet for {item} but the seller cancelled at the last minute.",
    "The seller stopped replying to my messages after I reserved {item}.",
    "I still have not received {item} despite multiple promises.",
    "The seller rescheduled our meet-up three times and never showed.",
]
DELIVERY_DETAILS = [
    "It has been days and I cannot get a response.",
    "This is the third time the pickup has fallen through.",
    "I took time off to meet them and they just did not come.",
    "They keep saying tomorrow but nothing ever happens.",
    "I am worried this was never going to be delivered at all.",
    "No explanation was given for the no-show.",
    "They changed the meeting location twice without warning.",
    "I have messaged them five times with no reply.",
]

PAYMENT_OPENERS = [
    "The seller is now asking for more money than we agreed for {item}.",
    "I paid for {item} but have not received a refund after it fell through.",
    "The price for {item} suddenly changed after I had already reserved it.",
    "I was asked to pay again for {item} even though I already paid once.",
    "The amount charged for {item} does not match what was listed.",
    "The seller will not refund my deposit for {item}.",
]
PAYMENT_DETAILS = [
    "This feels like an attempt to overcharge me.",
    "I have proof of the original agreed price.",
    "They are refusing to explain the extra charge.",
    "I want my money back since the deal did not go through.",
    "The listed price and the price they are asking for are very different.",
    "This is not what we agreed on at all.",
    "I should not have to pay twice for the same item.",
    "The refund was promised within two days but never came.",
]

OTHER_OPENERS = [
    "The seller was extremely rude during our conversation about {item}.",
    "I am not happy with how this transaction for {item} was handled overall.",
    "There was a general communication problem throughout this sale of {item}.",
    "The seller's attitude regarding {item} was unprofessional.",
    "Something felt off about the whole exchange for {item}, though I cannot pin down one issue.",
    "I have a general complaint about how {item} was handled that does not fit elsewhere.",
]
OTHER_DETAILS = [
    "They were dismissive whenever I asked questions.",
    "The tone of every message was disrespectful.",
    "It just did not feel like a fair or normal transaction.",
    "I would not want to deal with this seller again.",
    "Nothing specific went wrong, but the experience was unpleasant.",
    "They argued with me instead of trying to resolve anything.",
    "Communication was vague and unhelpful the whole time.",
    "I am filing this mostly to flag the poor conduct.",
]

CLOSINGS = [
    "",
    " Please help resolve this.",
    " I would like this looked into.",
    " I am requesting a review of this transaction.",
    " I hope this can be sorted out quickly.",
]

CATEGORIES = {
    "misdescribed_item": (MISDESCRIBED_OPENERS, MISDESCRIBED_DETAILS),
    "delivery_issue": (DELIVERY_OPENERS, DELIVERY_DETAILS),
    "payment_issue": (PAYMENT_OPENERS, PAYMENT_DETAILS),
    "other": (OTHER_OPENERS, OTHER_DETAILS),
}

# A handful of hand-written rows per category that deliberately borrow
# vocabulary from another category (e.g. a delivery complaint that also
# mentions a refund). The templated rows above use non-overlapping phrase
# banks per category, which a classifier separates perfectly - a 100%
# test-set accuracy is a sign of an unrealistically easy dataset, not a
# good model, and would read as suspicious in a report. These rows exist
# to give the classifier some genuine ambiguity to get wrong, the same way
# real dispute text would.
AMBIGUOUS_ROWS: dict[str, list[str]] = {
    "misdescribed_item": [
        "The laptop the seller finally brought after being three days late was also not the model listed.",
        "After a confusing back-and-forth about where to meet, the phone I received was scratched and not as described.",
        "The seller argued with me about the price, and once I got the jacket it was a completely different colour.",
        "The textbook was missing pages, and on top of that the seller was rude when I brought it up.",
        "The bicycle they eventually handed over, after cancelling twice, had a different frame than advertised.",
        "The monitor did not match the listing, and the seller also tried to charge me more at pickup.",
    ],
    "delivery_issue": [
        "I paid a deposit for the desk lamp but the seller has gone silent and never arranged a handover.",
        "The seller wanted extra money before they would even agree on a meet-up time for the kettle.",
        "After I paid, the seller kept postponing the handover of the headphones and now is not responding.",
        "The chair pickup fell through three times, and each time the seller mentioned wanting a higher price.",
        "The guitar was never delivered, and the seller is now asking for the payment to be sent again first.",
        "The seller cancelled the handover for the tablet after I raised concerns about the condition in photos.",
    ],
    "payment_issue": [
        "The seller wants more money for the backpack now, and also keeps rescheduling the pickup.",
        "I was overcharged for the mattress, which also arrived looking more worn than the listing suggested.",
        "The refund for the printer has not come through, and the seller has also stopped replying about pickup.",
        "The seller asked me to pay again for the shoes, and separately the pair sent was the wrong size.",
        "I am disputing the amount charged for the speaker, which also did not turn on when I tested it.",
        "The deposit for the mini fridge was never refunded after the seller missed our third meet-up.",
    ],
    "other": [
        "The whole exchange for the calculator was unpleasant, on top of it arriving a bit later than agreed.",
        "The seller was rude when I asked about the price of the microwave, though the item itself was fine.",
        "I am not happy with the seller's attitude, separate from the fact the fridge took a while to hand over.",
        "The seller was dismissive when I mentioned the jacket looked slightly different, but it was still wearable.",
        "Communication was poor throughout, and there was a small mix-up over the pickup time as well.",
        "I would not deal with this seller again, mostly because of how they spoke to me during the handover delay.",
    ],
}


def build_rows(rng: random.Random) -> list[tuple[str, str]]:
    rows: list[tuple[str, str]] = []

    for category, (openers, details) in CATEGORIES.items():
        ambiguous_for_category = AMBIGUOUS_ROWS[category]
        templated_target = ROWS_PER_CATEGORY - len(ambiguous_for_category)

        seen: set[str] = set(ambiguous_for_category)
        templated_rows: list[str] = []

        while len(templated_rows) < templated_target:
            item = rng.choice(ITEMS)
            opener = rng.choice(openers).format(item=item)
            detail = rng.choice(details)
            closing = rng.choice(CLOSINGS)
            text = f"{opener} {detail}{closing}"

            if text not in seen:
                seen.add(text)
                templated_rows.append(text)

        for text in ambiguous_for_category:
            rows.append((text, category))

        for text in templated_rows:
            rows.append((text, category))

    rng.shuffle(rows)

    return rows


def main() -> None:
    rng = random.Random(SEED)
    rows = build_rows(rng)

    output_path = Path(__file__).parent / "disputes.csv"

    with output_path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(["text", "category"])
        writer.writerows(rows)

    print(f"Wrote {len(rows)} synthetic rows to {output_path}")


if __name__ == "__main__":
    main()
