<?php

use App\Livewire\Transactions\Tracker;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InspectionService;
use App\Services\ReservationService;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

function categoryWithMode(string $mode): Category
{
    return Category::factory()->create(['transaction_mode' => $mode]);
}

/**
 * Goes through the real reservation -> seller-selects-buyer path (the
 * existing, unchanged flow), so the transaction_mode snapshot is taken
 * exactly the way a real purchase takes it - never set directly.
 */
function purchase(Category $category): array
{
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create(['user_id' => $seller->id, 'category_id' => $category->id, 'status' => 'active']);

    $reservation = app(ReservationService::class)->reserve($listing, $buyer);
    $transaction = app(ReservationService::class)->selectBuyer($reservation, $seller);

    return [$transaction, $buyer, $seller, $listing];
}

describe('Test 1 - Food & Beverages (DIRECT)', function () {
    it('creates a DIRECT transaction with no inspection or escrow step', function () {
        $food = categoryWithMode(Category::MODE_DIRECT);
        [$transaction, $buyer] = purchase($food);

        expect($transaction->transaction_mode)->toBe('DIRECT')
            ->and($transaction->status)->toBe('initiated');

        // No handover code is ever generated for a direct transaction.
        Livewire::actingAs($buyer)->test(Tracker::class, ['transaction' => $transaction]);
        expect($transaction->fresh()->handover_otp_plain)->toBeNull()
            ->and($transaction->fresh()->handover_code_plain)->toBeNull();
    });

    it('lets the buyer complete the purchase directly, skipping straight to COMPLETED', function () {
        $food = categoryWithMode(Category::MODE_DIRECT);
        [$transaction, $buyer, , $listing] = purchase($food);

        Livewire::actingAs($buyer)->test(Tracker::class, ['transaction' => $transaction])
            ->call('completeDirectPurchase')
            ->assertDispatched('notify', type: 'success');

        expect($transaction->fresh()->status)->toBe('COMPLETED')
            ->and($listing->fresh()->status)->toBe('sold');
    });

    it('never shows inspection/escrow wording for a direct purchase', function () {
        $food = categoryWithMode(Category::MODE_DIRECT);
        [$transaction, $buyer] = purchase($food);

        Livewire::actingAs($buyer)->test(Tracker::class, ['transaction' => $transaction])
            ->assertDontSee('Waiting for inspection')
            ->assertDontSee('inspection window', false)
            ->assertDontSee('Escrow', false)
            ->assertSee('Complete purchase');
    });
});

describe('Test 2/3 - Phones & Electronics (INSPECTION)', function () {
    it('keeps the existing handover code + inspection workflow intact', function () {
        $electronics = categoryWithMode(Category::MODE_INSPECTION);
        [$transaction, $buyer] = purchase($electronics);

        expect($transaction->transaction_mode)->toBe('INSPECTION');

        Livewire::actingAs($buyer)->test(Tracker::class, ['transaction' => $transaction])
            ->assertSee('Your handover code')
            ->assertDontSee('Complete purchase');

        expect($transaction->fresh()->handover_otp_plain)->not->toBeNull();
    });
});

describe('Test 5 - Other (default safe fallback)', function () {
    it('treats Other as INSPECTION unless an admin configures it otherwise', function () {
        $other = Category::where('slug', 'other')->first();

        expect($other)->not->toBeNull()
            ->and($other->transaction_mode)->toBe('INSPECTION')
            ->and($other->isInspection())->toBeTrue();
    });
});

describe('Test 6 - backend security', function () {
    it('refuses to direct-complete a transaction that requires inspection, even called directly', function () {
        $electronics = categoryWithMode(Category::MODE_INSPECTION);
        [$transaction, $buyer] = purchase($electronics);

        expect(fn () => app(InspectionService::class)->completeDirectPurchase($transaction, $buyer))
            ->toThrow(InvalidArgumentException::class, 'requires the inspection/escrow process');

        expect($transaction->fresh()->status)->toBe('initiated');
    });

    it('does not let the buyer bypass inspection via the tracker on an INSPECTION-mode transaction', function () {
        $electronics = categoryWithMode(Category::MODE_INSPECTION);
        [$transaction, $buyer] = purchase($electronics);

        // Nothing in the request can flip this - completeDirectPurchase is
        // gated by the transaction's own server-set snapshot, not any input.
        Livewire::actingAs($buyer)->test(Tracker::class, ['transaction' => $transaction])
            ->call('completeDirectPurchase')
            ->assertDispatched('notify', type: 'error');

        expect($transaction->fresh()->status)->not->toBe('COMPLETED');
    });

    it('does not let a direct-mode transaction be completed through the inspection-only completion path', function () {
        $food = categoryWithMode(Category::MODE_DIRECT);
        [$transaction, $buyer] = purchase($food);

        expect(fn () => app(InspectionService::class)->confirmItemAcceptance($transaction, $buyer))
            ->toThrow(DomainException::class);

        expect($transaction->fresh()->status)->toBe('initiated');
    });

    it('never trusts a client-supplied transaction_mode - it is always derived from the listing\'s category', function () {
        $electronics = categoryWithMode(Category::MODE_INSPECTION);
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id, 'category_id' => $electronics->id, 'status' => 'active']);
        $reservation = Reservation::create(['listing_id' => $listing->id, 'buyer_id' => $buyer->id, 'status' => Reservation::STATUS_ACTIVE]);

        // selectBuyer() takes no transaction_mode parameter at all - there is
        // no request field to tamper with in the first place.
        $transaction = app(ReservationService::class)->selectBuyer($reservation, $seller);

        expect($transaction->transaction_mode)->toBe('INSPECTION');
    });
});

describe('Test 7 - historical transactions keep the mode that applied when created', function () {
    it('does not retroactively change a transaction after its category\'s mode is later edited', function () {
        $category = categoryWithMode(Category::MODE_DIRECT);
        [$transaction, $buyer, , $listing] = purchase($category);

        expect($transaction->transaction_mode)->toBe('DIRECT');

        // Admin later reconfigures the category to require inspection.
        $category->update(['transaction_mode' => Category::MODE_INSPECTION]);

        expect($transaction->fresh()->transaction_mode)->toBe('DIRECT');

        // The existing transaction can still be completed the direct way.
        Livewire::actingAs($buyer)->test(Tracker::class, ['transaction' => $transaction])
            ->call('completeDirectPurchase');

        expect($transaction->fresh()->status)->toBe('COMPLETED')
            ->and($listing->fresh()->status)->toBe('sold');
    });

    it('gives a brand new transaction on the same (now reconfigured) category the new mode', function () {
        $category = categoryWithMode(Category::MODE_DIRECT);
        $category->update(['transaction_mode' => Category::MODE_INSPECTION]);

        [$transaction] = purchase($category->fresh());

        expect($transaction->transaction_mode)->toBe('INSPECTION');
    });
});

describe('category_id is snapshotted on the transaction, not a live join', function () {
    it('stores the listing\'s category_id on the transaction at creation time', function () {
        $category = categoryWithMode(Category::MODE_DIRECT);
        [$transaction, , , $listing] = purchase($category);

        expect($transaction->category_id)->toBe($listing->category_id);
    });

    it('keeps a completed transaction\'s category_id even if the listing is later recategorised', function () {
        $books = categoryWithMode(Category::MODE_DIRECT);
        $electronics = categoryWithMode(Category::MODE_INSPECTION);
        [$transaction, , , $listing] = purchase($books);

        $listing->update(['category_id' => $electronics->id]);

        expect($transaction->fresh()->category_id)->toBe($books->id)
            ->and($transaction->fresh()->category_id)->not->toBe($listing->fresh()->category_id);
    });
});

describe('listing detail page messaging', function () {
    it('shows direct-purchase steps for a DIRECT category and not the escrow copy', function () {
        $food = categoryWithMode(Category::MODE_DIRECT);
        $listing = Listing::factory()->create(['category_id' => $food->id, 'status' => 'active']);

        $this->get(route('listings.show', $listing))
            ->assertSee('does not need an inspection window')
            ->assertDontSee('48-hour inspection window');
    });

    it('shows inspection-window steps for an INSPECTION category', function () {
        $electronics = categoryWithMode(Category::MODE_INSPECTION);
        $listing = Listing::factory()->create(['category_id' => $electronics->id, 'status' => 'active']);

        $this->get(route('listings.show', $listing))
            ->assertSee('48-hour inspection window')
            ->assertDontSee('does not need an inspection window');
    });
});
