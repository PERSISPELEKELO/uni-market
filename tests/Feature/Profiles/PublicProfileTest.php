<?php

use App\Livewire\Profiles\PublicProfile;
use App\Models\Listing;
use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('shows the public information for a student', function () {
    $user = User::factory()->create([
        'name' => 'Chileshe Mwansa',
        'bio' => 'I sell textbooks and dorm gear.',
        'programme' => 'BSc Computer Science',
        'business_type' => 'Reseller',
    ]);

    $this->get(route('profiles.show', $user))
        ->assertOk()
        ->assertSee('Chileshe Mwansa')
        ->assertSee('I sell textbooks and dorm gear.')
        ->assertSee('BSc Computer Science')
        ->assertSee('Reseller')
        ->assertSee('Official Student');
});

it('never exposes private account information', function () {
    $user = User::factory()->create([
        'email' => 'private@example.com',
        'student_id' => '2024198273',
        'phone_number' => '+260971234567',
    ]);

    $this->get(route('profiles.show', $user))
        ->assertDontSee('private@example.com')
        ->assertDontSee('2024198273')
        ->assertDontSee('+260971234567');
});

it('omits optional sections that have not been filled in', function () {
    $user = User::factory()->create(['bio' => null, 'programme' => null, 'business_type' => null]);

    $this->get(route('profiles.show', $user))->assertOk()->assertDontSee('About');
});

it('shows a link to the student\'s active listings', function () {
    $seller = User::factory()->create();
    Listing::factory()->count(2)->create(['user_id' => $seller->id]);
    Listing::factory()->sold()->create(['user_id' => $seller->id]);

    $this->get(route('profiles.show', $seller))->assertSee('2 items');
});

it('returns 404 for a student who does not exist', function () {
    $this->get('/students/999999')->assertNotFound();
});

it('is linked from a listing\'s seller card', function () {
    $listing = Listing::factory()->create();

    $this->get(route('listings.show', $listing))->assertSee(route('profiles.show', $listing->seller), false);
});

describe('rating display', function () {
    it('shows "no ratings yet" and hides the reviews card when there are none', function () {
        $user = User::factory()->create();

        $this->get(route('profiles.show', $user))
            ->assertSee('No ratings yet')
            ->assertDontSee('5 star');
    });

    it('shows the average, count, breakdown and recent reviews once rated', function () {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create(['name' => 'Happy Buyer']);
        $transaction = Transaction::factory()->create([
            'seller_id' => $seller->id,
            'buyer_id' => $reviewer->id,
            'status' => 'COMPLETED',
        ]);
        Rating::create([
            'transaction_id' => $transaction->id,
            'rater_id' => $reviewer->id,
            'rated_id' => $seller->id,
            'stars' => 5,
            'comment' => 'Item exactly as described, great communication.',
        ]);

        $this->get(route('profiles.show', $seller))
            ->assertSee('5.0')
            ->assertSee('1 rating')
            ->assertSee('Happy Buyer')
            ->assertSee('Item exactly as described, great communication.');
    });

    it('never shows which listing or transaction a review came from', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id, 'title' => 'Secret Listing Title 12345']);
        $transaction = Transaction::factory()->create(['listing_id' => $listing->id, 'seller_id' => $seller->id, 'status' => 'COMPLETED']);
        Rating::create(['transaction_id' => $transaction->id, 'rater_id' => $transaction->buyer_id, 'rated_id' => $seller->id, 'stars' => 5]);

        $this->get(route('profiles.show', $seller))->assertDontSee('Secret Listing Title 12345');
    });
});

describe('messaging from a profile', function () {
    it('sends a guest to log in', function () {
        $user = User::factory()->create();

        Livewire::test(PublicProfile::class, ['user' => $user])
            ->call('message')
            ->assertRedirect(route('login'));
    });

    it('sends a signed-in visitor to a chat with that student', function () {
        $viewer = User::factory()->create();
        $profileOwner = User::factory()->create();

        Livewire::actingAs($viewer)->test(PublicProfile::class, ['user' => $profileOwner])
            ->call('message')
            ->assertRedirect(route('chat.thread', ['receiver' => $profileOwner->id]));
    });

    it('does not offer a message button on your own profile', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profiles.show', $user))->assertDontSee('wire:click="message"', false);
    });
});
