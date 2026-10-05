<?php

use App\Livewire\Marketplace\EditListing;
use App\Livewire\Marketplace\ListingIndex;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

function editPrice(Listing $listing, string $newPrice)
{
    return Livewire::actingAs($listing->seller)
        ->test(EditListing::class, ['listing' => $listing])
        ->set('form.price', $newPrice)
        ->call('save');
}

/**
 * A listing with at least one existing photo, so ListingForm's "at least one
 * photo" validation rule (unrelated to price drops) never gets in the way of
 * a price-only edit in these tests.
 */
function priceTestListing(array $overrides = []): Listing
{
    return Listing::factory()->create(array_merge(['images' => ['listings/placeholder.jpg']], $overrides));
}

describe('Test A - a new listing', function () {
    it('is never a hot deal', function () {
        $listing = Listing::factory()->create(['price' => 5000]);

        expect($listing->isHotDeal())->toBeFalse()
            ->and($listing->previous_price)->toBeNull();
    });
});

describe('Test B - price reduced', function () {
    it('becomes a hot deal with the correct saved amount and percentage', function () {
        $listing = priceTestListing(['price' => 5000]);

        editPrice($listing, '4000')->assertHasNoErrors();
        $listing->refresh();

        expect($listing->isHotDeal())->toBeTrue()
            ->and((float) $listing->previous_price)->toBe(5000.0)
            ->and((float) $listing->price)->toBe(4000.0)
            ->and($listing->discountAmount())->toBe(1000.0)
            ->and($listing->discountPercentage())->toBe(20);
    });

    it('shows the Hot Deal badge and both prices on the listing page', function () {
        $listing = priceTestListing(['price' => 5000]);
        editPrice($listing, '4000');

        $this->get(route('listings.show', $listing->fresh()))
            ->assertSee('Hot Deal', false)
            ->assertSee('5,000.00')
            ->assertSee('4,000.00')
            ->assertSee('Save K1,000.00', false)
            ->assertSee('20% off', false);
    });
});

describe('Test C - price increased', function () {
    it('is never marked as a hot deal', function () {
        $listing = priceTestListing(['price' => 4000]);

        editPrice($listing, '4500');
        $listing->refresh();

        expect($listing->isHotDeal())->toBeFalse()
            ->and($listing->previous_price)->toBeNull();
    });

    it('does not show a Hot Deal badge or a struck-through price', function () {
        $listing = priceTestListing(['price' => 4000]);
        editPrice($listing, '4500');

        $this->get(route('listings.show', $listing->fresh()))
            ->assertDontSee('Hot Deal', false)
            ->assertDontSee('line-through', false);
    });
});

describe('Test D - same price', function () {
    it('creates no price-drop event at all', function () {
        $listing = priceTestListing(['price' => 4500]);

        editPrice($listing, '4500.00');
        $listing->refresh();

        expect($listing->isHotDeal())->toBeFalse()
            ->and($listing->previous_price)->toBeNull()
            ->and($listing->price_dropped_at)->toBeNull();
    });

    it('leaves an existing hot deal exactly as it was if the price is re-saved unchanged', function () {
        $listing = priceTestListing(['price' => 5000]);
        editPrice($listing, '4000');
        $listing->refresh();
        $droppedAt = $listing->price_dropped_at;

        editPrice($listing, '4000.00');
        $listing->refresh();

        expect($listing->isHotDeal())->toBeTrue()
            ->and((float) $listing->previous_price)->toBe(5000.0)
            ->and($listing->price_dropped_at->eq($droppedAt))->toBeTrue();
    });
});

describe('Test E - multiple reductions compare against the immediately previous price', function () {
    it('never compares the current price against an older, superseded price', function () {
        $listing = priceTestListing(['price' => 5000]);

        editPrice($listing, '4500');
        $listing->refresh();
        expect((float) $listing->previous_price)->toBe(5000.0);

        editPrice($listing, '4000');
        $listing->refresh();

        expect((float) $listing->previous_price)->toBe(4500.0) // not 5000
            ->and((float) $listing->price)->toBe(4000.0)
            ->and($listing->discountAmount())->toBe(500.0)
            ->and($listing->isHotDeal())->toBeTrue();
    });
});

describe('Test F - a price increase after a reduction removes the active hot deal', function () {
    it('does not falsely imply a discount from the original, much older price', function () {
        $listing = priceTestListing(['price' => 5000]);

        editPrice($listing, '4000');
        editPrice($listing->fresh(), '4500');
        $listing->refresh();

        expect($listing->isHotDeal())->toBeFalse()
            ->and($listing->previous_price)->toBeNull();

        $this->get(route('listings.show', $listing))
            ->assertDontSee('5,000.00')
            ->assertDontSee('Hot Deal', false);
    });
});

describe('Test G - a new reduction after that starts a fresh hot deal', function () {
    it('creates a new price-drop event measured from the current (increased) price', function () {
        $listing = priceTestListing(['price' => 5000]);
        editPrice($listing, '4000');
        editPrice($listing->fresh(), '4500'); // increase clears the deal

        editPrice($listing->fresh(), '3800');
        $listing->refresh();

        expect($listing->isHotDeal())->toBeTrue()
            ->and((float) $listing->previous_price)->toBe(4500.0)
            ->and((float) $listing->price)->toBe(3800.0);
    });
});

describe('security', function () {
    it('always computes the previous price from the real database value, never from anything the form could submit', function () {
        $listing = priceTestListing(['price' => 5000]);

        // The Livewire form has no previous_price property at all - there is
        // nothing to tamper with - but this proves the *value* used is always
        // the real stored price regardless of what the request looks like.
        $component = Livewire::actingAs($listing->seller)->test(EditListing::class, ['listing' => $listing]);

        expect(property_exists($component->instance()->form, 'previous_price'))->toBeFalse();

        $component->set('form.price', '4000')->call('save');

        expect((float) $listing->fresh()->previous_price)->toBe(5000.0);
    });

    it('does not let anyone but the listing owner change its price', function () {
        $listing = priceTestListing(['price' => 5000]);
        $stranger = User::factory()->create();

        Livewire::actingAs($stranger)
            ->test(EditListing::class, ['listing' => $listing])
            ->assertNotFound();

        expect((float) $listing->fresh()->price)->toBe(5000.0);
    });

    it('rejects a negative or non-numeric price on a price edit', function (string $price) {
        $listing = priceTestListing(['price' => 5000]);

        editPrice($listing, $price)->assertHasErrors('form.price');

        expect((float) $listing->fresh()->price)->toBe(5000.0);
    })->with(['negative' => '-100', 'text' => 'cheap']);
});

describe('the Hot Deals section', function () {
    it('only ever shows listings with a genuine, currently active price reduction', function () {
        $genuine = Listing::factory()->create(['price' => 4000, 'previous_price' => 5000, 'price_dropped_at' => now(), 'status' => 'active']);
        $noHistory = Listing::factory()->create(['price' => 3000, 'status' => 'active']);
        $increased = Listing::factory()->create(['price' => 4500, 'previous_price' => 4000, 'price_dropped_at' => now(), 'status' => 'active']); // stale: previous < current

        Livewire::test(ListingIndex::class)
            ->assertViewHas('hotDeals', function ($hotDeals) use ($genuine, $noHistory, $increased) {
                return $hotDeals->pluck('id')->contains($genuine->id)
                    && ! $hotDeals->pluck('id')->contains($noHistory->id)
                    && ! $hotDeals->pluck('id')->contains($increased->id);
            });
    });

    it('lets a visitor filter the marketplace to hot deals only', function () {
        Listing::factory()->create(['price' => 4000, 'previous_price' => 5000, 'price_dropped_at' => now(), 'status' => 'active', 'title' => 'Discounted Laptop']);
        Listing::factory()->create(['price' => 3000, 'status' => 'active', 'title' => 'Full Price Chair']);

        Livewire::test(ListingIndex::class)
            ->call('toggleHotDeals')
            ->assertSee('Discounted Laptop')
            ->assertDontSee('Full Price Chair');
    });
});
