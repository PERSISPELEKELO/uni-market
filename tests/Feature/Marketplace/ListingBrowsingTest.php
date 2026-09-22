<?php

use App\Livewire\Marketplace\ListingIndex;
use App\Livewire\Marketplace\ListingShow;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

describe('browsing', function () {
    it('shows the reservation count on a listing card, and hides it when there are none', function () {
        $withReservations = Listing::factory()->create(['title' => 'Popular textbook']);
        Reservation::factory()->for($withReservations)->count(2)->create();
        $withoutReservations = Listing::factory()->create(['title' => 'Quiet listing']);

        $this->get(route('listings.index'))
            ->assertSee('Popular textbook')
            ->assertSee('2 reservations')
            ->assertSee('Quiet listing');

        // The "Quiet listing" card renders with no reservation count text at all.
        Livewire::test(ListingIndex::class)
            ->assertViewHas('listings', function ($listings) use ($withoutReservations) {
                $card = $listings->firstWhere('id', $withoutReservations->id);

                return ($card->active_reservations_count ?? 0) === 0;
            });
    });

    it('lists only active listings with their key details', function () {
        $category = Category::factory()->create(['name' => 'Textbooks']);
        Listing::factory()->create(['title' => 'Chemistry notes bundle', 'price' => 120, 'condition' => 'like_new', 'category_id' => $category->id]);
        Listing::factory()->sold()->create(['title' => 'Already sold item']);
        Listing::factory()->pending()->create(['title' => 'Reserved item']);
        Listing::factory()->suspended()->create(['title' => 'Suspended item']);

        $this->get(route('listings.index'))
            ->assertOk()
            ->assertSee('Chemistry notes bundle')
            ->assertSee('K120.00')
            ->assertSee('Like new')
            ->assertSee('Textbooks')
            ->assertDontSee('Already sold item')
            ->assertDontSee('Reserved item')
            ->assertDontSee('Suspended item');
    });

    it('searches titles and descriptions', function () {
        Listing::factory()->create(['title' => 'Mountain bike', 'description' => 'Fast and light']);
        Listing::factory()->create(['title' => 'Desk lamp', 'description' => 'Bright reading light']);

        Livewire::test(ListingIndex::class)
            ->set('search', 'bike')
            ->assertSee('Mountain bike')
            ->assertDontSee('Desk lamp')
            ->set('search', 'reading')
            ->assertSee('Desk lamp')
            ->assertDontSee('Mountain bike');
    });

    it('treats percent and underscore in a search as plain characters', function () {
        Listing::factory()->create(['title' => 'Mountain bike']);

        Livewire::test(ListingIndex::class)
            ->set('search', '%')
            ->assertDontSee('Mountain bike')
            ->assertSee('No listings match your search');
    });

    it('filters by category and condition and can clear the filters', function () {
        $books = Category::factory()->create();
        $tech = Category::factory()->create();
        Listing::factory()->create(['title' => 'Physics textbook', 'category_id' => $books->id, 'condition' => 'fair']);
        Listing::factory()->create(['title' => 'Gaming laptop', 'category_id' => $tech->id, 'condition' => 'new']);

        Livewire::test(ListingIndex::class)
            ->call('selectCategory', $books->id)
            ->assertSee('Physics textbook')
            ->assertDontSee('Gaming laptop')
            ->call('clearFilters')
            ->call('setCondition', 'new')
            ->assertSee('Gaming laptop')
            ->assertDontSee('Physics textbook')
            ->call('clearFilters')
            ->assertSee('Physics textbook')
            ->assertSee('Gaming laptop');
    });

    it('sorts by price', function () {
        Listing::factory()->create(['title' => 'Cheap thing', 'price' => 10]);
        Listing::factory()->create(['title' => 'Pricey thing', 'price' => 900]);

        Livewire::test(ListingIndex::class)
            ->set('sortBy', 'price_desc')
            ->assertSeeInOrder(['Pricey thing', 'Cheap thing'])
            ->set('sortBy', 'price_asc')
            ->assertSeeInOrder(['Cheap thing', 'Pricey thing']);
    });

    it('falls back safely when given an unknown sort or condition', function () {
        Listing::factory()->create(['title' => 'Only item']);

        Livewire::test(ListingIndex::class)
            ->set('sortBy', 'drop table')
            ->set('conditionFilter', 'mint')
            ->assertSee('Only item');
    });

    it('paginates the marketplace', function () {
        Listing::factory()->count(15)->create();

        Livewire::test(ListingIndex::class)
            ->assertViewHas('listings', fn ($listings) => $listings->total() === 15 && $listings->count() === 12 && $listings->lastPage() === 2)
            ->call('nextPage')
            ->assertViewHas('listings', fn ($listings) => $listings->count() === 3);
    });

    it('shows an inviting empty state when nothing is for sale', function () {
        $this->get(route('listings.index'))->assertOk()->assertSee('Nothing for sale yet');
    });
});

describe('viewing a listing', function () {
    it('shows the details a buyer needs without exposing the seller\'s student id', function () {
        $seller = User::factory()->create(['name' => 'Mwamba Bwalya', 'student_id' => '2024883912']);
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'title' => 'MacBook Air M1',
            'description' => 'Battery health 89 percent.',
            'price' => 7500,
            'condition' => 'good',
        ]);

        $this->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('MacBook Air M1')
            ->assertSee('K7,500.00')
            ->assertSee('Good')
            ->assertSee('Battery health 89 percent.')
            ->assertSee('Mwamba Bwalya')
            ->assertSee('Official Student')
            ->assertDontSee('2024883912');
    });

    it('returns a friendly 404 for a listing that does not exist', function () {
        $this->get(route('listings.show', 99999))
            ->assertNotFound()
            ->assertSee('We could not find that page');
    });

    it('hides suspended listings from everyone except their seller', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->suspended()->create(['user_id' => $seller->id]);

        $this->get(route('listings.show', $listing))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('listings.show', $listing))->assertNotFound();
        $this->actingAs($seller)->get(route('listings.show', $listing))->assertOk();
    });

    it('offers the seller edit controls but no purchase button on their own listing', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id]);

        $this->actingAs($seller)->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('This is your listing.')
            ->assertSee('Edit listing')
            ->assertDontSee('Reserve this item');
    });
});

describe('reserving an item', function () {
    it('sends guests to the login page', function () {
        $listing = Listing::factory()->create();

        Livewire::test(ListingShow::class, ['listing' => $listing])
            ->call('reserve')
            ->assertRedirect(route('login'));

        expect(Reservation::count())->toBe(0);
    });

    it('reserves an available item for a buyer without starting a transaction or hiding the listing', function () {
        $listing = Listing::factory()->create(['price' => 300]);
        $buyer = User::factory()->create();

        Livewire::actingAs($buyer)->test(ListingShow::class, ['listing' => $listing])
            ->call('reserve')
            ->assertDispatched('notify', type: 'success');

        $reservation = Reservation::firstOrFail();

        expect($reservation->buyer_id)->toBe($buyer->id)
            ->and($reservation->listing_id)->toBe($listing->id)
            ->and($reservation->status)->toBe(Reservation::STATUS_ACTIVE)
            ->and($listing->fresh()->status)->toBe('active')
            ->and(Transaction::count())->toBe(0);
    });

    it('reserving twice is a harmless no-op, not a duplicate reservation', function () {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();

        $component = Livewire::actingAs($buyer)->test(ListingShow::class, ['listing' => $listing]);
        $component->call('reserve');
        $component->call('reserve');

        expect(Reservation::count())->toBe(1);
    });

    it('shows the reservation count to any visitor and the "already reserved" state to the buyer', function () {
        $listing = Listing::factory()->create();
        User::factory()->create()->reservations()->create(['listing_id' => $listing->id, 'status' => Reservation::STATUS_ACTIVE]);
        $buyer = User::factory()->create();

        $this->get(route('listings.show', $listing))->assertSee('1 reservation');

        Livewire::actingAs($buyer)->test(ListingShow::class, ['listing' => $listing])
            ->call('reserve')
            ->assertSee('2 reservations')
            ->assertSee('reserved this item');
    });

    it('does not let a seller reserve their own item', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id]);

        Livewire::actingAs($seller)->test(ListingShow::class, ['listing' => $listing])
            ->call('reserve')
            ->assertDispatched('notify', type: 'error');

        expect(Reservation::count())->toBe(0);
    });

    it('does not let anyone reserve an item that is no longer active', function () {
        $listing = Listing::factory()->pending()->create();
        $buyer = User::factory()->create();

        Livewire::actingAs($buyer)->test(ListingShow::class, ['listing' => $listing])
            ->call('reserve')
            ->assertDispatched('notify', type: 'error');

        expect(Reservation::count())->toBe(0);
    });
});

describe('selecting a buyer', function () {
    it('lets the seller choose one reservation, starting the transaction and cancelling the rest', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id, 'price' => 500]);
        $chosen = Reservation::factory()->for($listing)->create();
        $other = Reservation::factory()->for($listing)->create();

        Livewire::actingAs($seller)->test(ListingShow::class, ['listing' => $listing])
            ->call('selectBuyer', $chosen->id)
            ->assertRedirect(route('transactions.tracker', ['transaction' => Transaction::first()->id]));

        $transaction = Transaction::firstOrFail();
        expect($transaction->buyer_id)->toBe($chosen->buyer_id)
            ->and((float) $transaction->amount)->toBe(500.0)
            ->and($listing->fresh()->status)->toBe('pending')
            ->and($chosen->fresh()->status)->toBe(Reservation::STATUS_SELECTED)
            ->and($other->fresh()->status)->toBe(Reservation::STATUS_CANCELLED);
    });

    it('does not let a random user select a buyer for someone else\'s listing', function () {
        $listing = Listing::factory()->create();
        $reservation = Reservation::factory()->for($listing)->create();

        Livewire::actingAs(User::factory()->create())->test(ListingShow::class, ['listing' => $listing])
            ->call('selectBuyer', $reservation->id)
            ->assertForbidden();

        expect($listing->fresh()->status)->toBe('active')->and(Transaction::count())->toBe(0);
    });

    it('does not let the buyer select themselves via a crafted request', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id]);
        $reservation = Reservation::factory()->for($listing)->create();

        Livewire::actingAs($reservation->buyer)->test(ListingShow::class, ['listing' => $listing])
            ->call('selectBuyer', $reservation->id)
            ->assertForbidden();

        expect(Transaction::count())->toBe(0);
    });

    it('refuses to act on a reservation that does not belong to this listing', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id]);
        $foreignReservation = Reservation::factory()->create();

        Livewire::actingAs($seller)->test(ListingShow::class, ['listing' => $listing])
            ->call('selectBuyer', $foreignReservation->id)
            ->assertDispatched('notify', type: 'error');

        expect(Transaction::count())->toBe(0);
    });
});
