<?php

use App\Livewire\Marketplace\ListingIndex;
use App\Livewire\Marketplace\ListingShow;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

describe('browsing', function () {
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
            ->call('initiatePurchase')
            ->assertRedirect(route('login'));

        expect(Transaction::count())->toBe(0);
    });

    it('reserves an available item for a buyer', function () {
        $listing = Listing::factory()->create(['price' => 300]);
        $buyer = User::factory()->create();

        Livewire::actingAs($buyer)->test(ListingShow::class, ['listing' => $listing])->call('initiatePurchase');

        $transaction = Transaction::firstOrFail();

        expect($transaction->buyer_id)->toBe($buyer->id)
            ->and($transaction->seller_id)->toBe($listing->user_id)
            ->and((float) $transaction->amount)->toBe(300.0)
            ->and($listing->fresh()->status)->toBe('pending');
    });

    it('does not let a seller buy their own item', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id]);

        Livewire::actingAs($seller)->test(ListingShow::class, ['listing' => $listing])->call('initiatePurchase');

        expect(Transaction::count())->toBe(0)->and($listing->fresh()->status)->toBe('active');
    });

    it('does not double-book an item that is already reserved', function () {
        $listing = Listing::factory()->create();
        $firstBuyer = User::factory()->create();
        $secondBuyer = User::factory()->create();

        $secondBuyerView = Livewire::actingAs($secondBuyer)->test(ListingShow::class, ['listing' => $listing]);

        Livewire::actingAs($firstBuyer)->test(ListingShow::class, ['listing' => $listing])->call('initiatePurchase');
        $secondBuyerView->call('initiatePurchase');

        expect(Transaction::count())->toBe(1);
    });

    it('rejects the buy endpoint for items that are no longer available', function () {
        $listing = Listing::factory()->pending()->create();
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->post(route('transactions.initiate', $listing))
            ->assertRedirect()
            ->assertSessionHas('error', 'Sorry, this item is no longer available.');

        expect(Transaction::count())->toBe(0);
    });
});
