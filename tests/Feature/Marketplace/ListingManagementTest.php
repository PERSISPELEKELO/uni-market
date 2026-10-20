<?php

use App\Livewire\Marketplace\CreateListing;
use App\Livewire\Marketplace\EditListing;
use App\Livewire\Marketplace\MyListings;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\NewListingPosted;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');
});

function validListingData(Category $category, array $overrides = []): array
{
    return array_merge([
        'form.category_id' => $category->id,
        'form.title' => 'Calculus 9th Edition textbook',
        'form.description' => 'Clean copy with light highlighting in chapter three.',
        'form.price' => '250.00',
        'form.condition' => 'good',
    ], $overrides);
}

function fillListingForm($component, array $data)
{
    foreach ($data as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

describe('creating a listing', function () {
    it('shows a "get verified to sell" screen instead of the form to a student who is not yet student-verified', function () {
        $user = User::factory()->pendingStudentVerification()->create();

        Livewire::actingAs($user)->test(CreateListing::class)
            ->assertSee('Only verified ZUT students can sell')
            ->assertSee('Upload my student ID')
            ->assertDontSee('Your listing title');
    });

    it('shows an email-verification prompt instead of the form to an email-unverified student', function () {
        $user = User::factory()->unverified()->create();

        Livewire::actingAs($user)->test(CreateListing::class)
            ->assertSee('Verify your email to continue')
            ->assertDontSee('Your listing title');
    });

    it('refuses to actually save a listing from an unverified student even if the save action is called directly', function () {
        $user = User::factory()->pendingStudentVerification()->create();
        $category = Category::factory()->create();

        Livewire::actingAs($user)->test(CreateListing::class)
            ->set('form.category_id', $category->id)
            ->set('form.title', 'Calculus 9th Edition textbook')
            ->set('form.description', 'Clean copy with light highlighting in chapter three.')
            ->set('form.price', '250.00')
            ->set('form.condition', 'good')
            ->set('form.images', [fakePhoto('front.png')])
            ->call('save')
            ->assertForbidden();

        expect(Listing::count())->toBe(0);
    });

    it('live-previews the listing as it is filled out', function () {
        $category = Category::factory()->create(['name' => 'Books & Textbooks']);

        $component = Livewire::actingAs(User::factory()->create())->test(CreateListing::class)
            ->assertSee('Your listing title');

        fillListingForm($component, validListingData($category))
            ->assertSee('Calculus 9th Edition textbook')
            ->assertSee('Books & Textbooks')
            ->assertSee('K250.00')
            ->assertSee('Good');
    });

    it('publishes a listing with photos for a signed-in student', function () {
        $seller = User::factory()->create();
        $category = Category::factory()->create();

        $component = Livewire::actingAs($seller)->test(CreateListing::class);
        fillListingForm($component, validListingData($category, [
            'form.images' => [fakePhoto('front.png'), fakePhoto('back.png')],
        ]))->call('save')->assertHasNoErrors();

        $listing = Listing::firstOrFail();

        expect($listing->user_id)->toBe($seller->id)
            ->and($listing->status)->toBe('active')
            ->and($listing->images)->toHaveCount(2)
            ->and((float) $listing->price)->toBe(250.0);

        collect($listing->images)->each(fn (string $path) => Storage::disk('public')->assertExists($path));

        $component->assertRedirect(route('listings.show', $listing));
    });

    it('notifies students subscribed to new-listing alerts, but never the poster themselves', function () {
        Notification::fake();

        $seller = User::factory()->create();
        $subscriber = User::factory()->create(['notify_new_listings' => true]);
        User::factory()->create(['notify_new_listings' => false]); // not subscribed
        $category = Category::factory()->create();

        $component = Livewire::actingAs($seller)->test(CreateListing::class);
        fillListingForm($component, validListingData($category, [
            'form.images' => [fakePhoto('front.png')],
        ]))->call('save');

        $listing = Listing::firstOrFail();

        Notification::assertSentTo($subscriber, NewListingPosted::class);
        Notification::assertNotSentTo($seller, NewListingPosted::class);
        Notification::assertCount(1);
    });

    it('does not notify anyone when a listing is only saved as a draft', function () {
        Notification::fake();

        User::factory()->create(['notify_new_listings' => true]);
        $category = Category::factory()->create();

        $component = Livewire::actingAs(User::factory()->create())->test(CreateListing::class);
        fillListingForm($component, validListingData($category, [
            'form.images' => [fakePhoto('front.png')],
        ]))->call('saveAsDraft');

        Notification::assertNothingSent();
    });

    it('shows a helpful message for every missing required field', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(CreateListing::class)
            ->set('form.condition', '')
            ->call('save')
            ->assertHasErrors(['form.category_id', 'form.title', 'form.description', 'form.price', 'form.condition', 'form.images'])
            ->assertSee('Please choose a category.')
            ->assertSee('Please give your item a title.')
            ->assertSee('Please describe your item.')
            ->assertSee('Please enter a price.')
            ->assertSee('Please upload at least one photo of your item.');

        expect(Listing::count())->toBe(0);
    });

    it('rejects invalid prices', function (string $price) {
        $category = Category::factory()->create();

        $component = Livewire::actingAs(User::factory()->create())->test(CreateListing::class);
        fillListingForm($component, validListingData($category, [
            'form.price' => $price,
            'form.images' => [fakePhoto('front.png')],
        ]))->call('save')->assertHasErrors('form.price');

        expect(Listing::count())->toBe(0);
    })->with(['negative' => '-5', 'zero' => '0', 'text' => 'free', 'too large' => '99999999']);

    it('rejects files that are not photos', function () {
        $category = Category::factory()->create();

        $component = Livewire::actingAs(User::factory()->create())->test(CreateListing::class);
        fillListingForm($component, validListingData($category, [
            'form.images' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
        ]))->assertHasErrors('form.images.0')->assertSet('form.images', []);

        expect(Listing::count())->toBe(0);
    });

    it('rejects photos over 3 MB', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(CreateListing::class)
            ->set('form.images', [fakePhoto('huge.png', 4096)])
            ->assertHasErrors('form.images.0');
    });

    it('allows at most four photos', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(CreateListing::class)
            ->set('form.images', collect(range(1, 5))->map(fn ($n) => fakePhoto("p{$n}.png"))->all())
            ->assertHasErrors('form.images');
    });

    it('rejects a category or condition that does not exist', function () {
        $category = Category::factory()->create();

        $component = Livewire::actingAs(User::factory()->create())->test(CreateListing::class);
        fillListingForm($component, validListingData($category, [
            'form.category_id' => 9999,
            'form.condition' => 'destroyed',
            'form.images' => [fakePhoto('front.png')],
        ]))->call('save')->assertHasErrors(['form.category_id', 'form.condition']);
    });

    it('saves as a draft, invisible to everyone but the seller, and publishable later', function () {
        $seller = User::factory()->create();
        $stranger = User::factory()->create();
        $category = Category::factory()->create();

        $component = Livewire::actingAs($seller)->test(CreateListing::class);
        fillListingForm($component, validListingData($category, [
            'form.images' => [fakePhoto('front.png')],
        ]))->call('saveAsDraft')->assertHasNoErrors()->assertRedirect(route('listings.mine'));

        $listing = Listing::firstOrFail();
        expect($listing->status)->toBe('draft');

        $this->get(route('listings.show', $listing))->assertOk(); // seller
        $this->actingAs($stranger)->get(route('listings.show', $listing))->assertNotFound();
        $this->get(route('listings.index'))->assertDontSee($listing->title);

        Livewire::actingAs($seller)->test(MyListings::class)
            ->call('publish', $listing->id);

        expect($listing->fresh()->status)->toBe('active');
        $this->get(route('listings.index'))->assertSee($listing->title);
    });

    it('stores markup as plain text and never as html', function () {
        $seller = User::factory()->create();
        $category = Category::factory()->create();

        $component = Livewire::actingAs($seller)->test(CreateListing::class);
        fillListingForm($component, validListingData($category, [
            'form.title' => '<script>alert("x")</script> Textbook',
            'form.images' => [fakePhoto('front.png')],
        ]))->call('save');

        $listing = Listing::firstOrFail();

        $this->get(route('listings.show', $listing))
            ->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    });
});

describe('editing a listing', function () {
    it('lets the seller update their own listing and clean up removed photos', function () {
        $seller = User::factory()->create();
        Storage::disk('public')->put('listings/old.jpg', 'old');
        $listing = Listing::factory()->create(['user_id' => $seller->id, 'images' => ['listings/old.jpg'], 'title' => 'Old title here']);

        Livewire::actingAs($seller)
            ->test(EditListing::class, ['listing' => $listing])
            ->assertSet('form.title', 'Old title here')
            ->set('form.title', 'Brand new title')
            ->set('form.price', '99.50')
            ->call('removeExistingPhoto', 0)
            ->set('form.images', [fakePhoto('new.png')])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('listings.show', $listing));

        $listing->refresh();

        expect($listing->title)->toBe('Brand new title')
            ->and((float) $listing->price)->toBe(99.5)
            ->and($listing->images)->toHaveCount(1)
            ->and($listing->images[0])->not->toBe('listings/old.jpg');

        Storage::disk('public')->assertMissing('listings/old.jpg');
        Storage::disk('public')->assertExists($listing->images[0]);
    });

    it('validates edits on the server', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id, 'images' => ['listings/a.jpg']]);

        Livewire::actingAs($seller)
            ->test(EditListing::class, ['listing' => $listing])
            ->set('form.title', 'abc')
            ->set('form.price', '-1')
            ->call('save')
            ->assertHasErrors(['form.title', 'form.price']);

        expect($listing->fresh()->title)->toBe($listing->title);
    });

    it('hides the edit page of someone else\'s listing behind a 404', function () {
        $listing = Listing::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('listings.edit', $listing))
            ->assertNotFound();
    });

    it('blocks a non-owner from loading the edit component at all', function () {
        $listing = Listing::factory()->create(['title' => 'Original title']);

        Livewire::actingAs(User::factory()->create())
            ->test(EditListing::class, ['listing' => $listing])
            ->assertNotFound();

        expect($listing->fresh()->title)->toBe('Original title');
    });

    it('does not allow editing a listing that a buyer has reserved', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->pending()->create(['user_id' => $seller->id]);

        $this->actingAs($seller)->get(route('listings.edit', $listing))->assertForbidden();
    });

    it('sends guests to the login page', function () {
        $this->get(route('listings.edit', Listing::factory()->create()))->assertRedirect(route('login'));
    });
});

describe('deleting a listing', function () {
    it('lets the seller remove their own listing', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id]);

        Livewire::actingAs($seller)->test(MyListings::class)->call('delete', $listing->id);

        $this->assertSoftDeleted($listing);
    });

    it('never lets a user delete another user\'s listing by guessing its id', function () {
        $listing = Listing::factory()->create();

        expect(fn () => Livewire::actingAs(User::factory()->create())->test(MyListings::class)->call('delete', $listing->id))
            ->toThrow(ModelNotFoundException::class);

        $this->assertNotSoftDeleted($listing);
    });

    it('does not let the seller remove a listing a buyer has reserved', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->pending()->create(['user_id' => $seller->id]);

        Livewire::actingAs($seller)->test(MyListings::class)->call('delete', $listing->id)->assertForbidden();

        $this->assertNotSoftDeleted($listing);
    });

    it('only lists the signed-in seller\'s own listings', function () {
        $seller = User::factory()->create();
        Listing::factory()->create(['user_id' => $seller->id, 'title' => 'My own bicycle']);
        Listing::factory()->create(['title' => 'Somebody elses laptop']);

        $this->actingAs($seller)->get(route('listings.mine'))
            ->assertOk()
            ->assertSee('My own bicycle')
            ->assertDontSee('Somebody elses laptop');
    });
});

describe('seller dashboard status tiles', function () {
    it('counts each listing exactly once, under the right tile', function () {
        $seller = User::factory()->create();
        Listing::factory()->suspended()->create(['user_id' => $seller->id]);
        Listing::factory()->create(['user_id' => $seller->id]); // active
        Listing::factory()->sold()->create(['user_id' => $seller->id]);
        Listing::factory()->create(['user_id' => $seller->id, 'status' => Listing::STATUS_DRAFT]);
        Listing::factory()->create(['user_id' => $seller->id, 'created_at' => now()->subDays(30)]); // to renew

        Livewire::actingAs($seller)->test(MyListings::class)
            ->assertViewHas('counts', fn ($counts) => $counts === [
                'needs_attention' => 1,
                'active' => 2,
                'sold' => 1,
                'draft' => 1,
                'to_renew' => 1,
            ]);
    });

    it('filters the list to one status at a time and can clear the filter', function () {
        $seller = User::factory()->create();
        Listing::factory()->create(['user_id' => $seller->id, 'title' => 'Active item']);
        Listing::factory()->sold()->create(['user_id' => $seller->id, 'title' => 'Sold item']);

        Livewire::actingAs($seller)->test(MyListings::class)
            ->call('setStatusFilter', 'sold')
            ->assertSee('Sold item')
            ->assertDontSee('Active item')
            ->call('setStatusFilter', 'sold')
            ->assertSee('Active item')
            ->assertSee('Sold item');
    });

    it('only ever shows and acts on the signed-in seller\'s own listings', function () {
        $seller = User::factory()->create();
        $other = Listing::factory()->create();

        expect(fn () => Livewire::actingAs($seller)->test(MyListings::class)->call('publish', $other->id))
            ->toThrow(ModelNotFoundException::class);
    });
});

describe('renewing and manually completing a listing', function () {
    it('lets the seller renew an ageing listing', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id, 'created_at' => now()->subDays(25)]);

        expect($listing->isDueForRenewal())->toBeTrue();

        Livewire::actingAs($seller)->test(MyListings::class)->call('renew', $listing->id);

        expect($listing->fresh()->isDueForRenewal())->toBeFalse();
    });

    it('marks an active listing sold without creating a transaction, and cancels its reservations', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id]);
        $reservation = Reservation::factory()->for($listing)->create();

        Livewire::actingAs($seller)->test(MyListings::class)->call('markSoldManually', $listing->id);

        expect($listing->fresh()->status)->toBe('sold')
            ->and($reservation->fresh()->status)->toBe(Reservation::STATUS_CANCELLED)
            ->and(Transaction::count())->toBe(0);
    });

    it('does not let marking sold or renewing apply to a listing that is not active', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->sold()->create(['user_id' => $seller->id]);

        Livewire::actingAs($seller)->test(MyListings::class)
            ->call('markSoldManually', $listing->id)
            ->call('renew', $listing->id);

        expect($listing->fresh()->status)->toBe('sold')
            ->and($listing->fresh()->renewed_at)->toBeNull();
    });
});

it('applies the listing policy consistently', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $active = Listing::factory()->create(['user_id' => $owner->id]);
    $reserved = Listing::factory()->pending()->create(['user_id' => $owner->id]);

    expect(Gate::forUser($owner)->allows('update', $active))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $reserved))->toBeFalse()
        ->and(Gate::forUser($stranger)->allows('update', $active))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('delete', $active))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $reserved))->toBeFalse()
        ->and(Gate::forUser($stranger)->allows('delete', $active))->toBeFalse();
});
