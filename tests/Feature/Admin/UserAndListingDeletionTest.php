<?php

use App\Filament\Resources\ListingResource\Pages\ListListings;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

describe('deleting a listing through the admin panel', function () {
    it('lets an admin soft-delete a listing and records it in the audit log', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $listing = Listing::factory()->create(['title' => 'Old Textbook']);

        Livewire::actingAs($admin)
            ->test(ListListings::class)
            ->callTableAction('delete_listing', $listing);

        expect(Listing::find($listing->id))->toBeNull()
            ->and(Listing::withTrashed()->find($listing->id))->not->toBeNull();

        expect(AuditLog::where('action', 'LISTING_DELETED')->where('target_id', $listing->id)->first()?->payload['title'])
            ->toBe('Old Textbook');
    });

    it('does not let a governance-committee member reach the listings page at all', function () {
        $governance = User::factory()->create(['role' => 'governance_committee']);

        $this->actingAs($governance)->get('/admin/listings')->assertForbidden();
    });
});

describe('deleting a user through the admin panel', function () {
    it('lets an admin delete an account with no history via the table action', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'student']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('delete_user', $target);

        expect(User::find($target->id))->toBeNull();
    });

    it('shows a friendly error instead of deleting an account with history', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'student']);
        Listing::factory()->create(['user_id' => $target->id]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('delete_user', $target)
            ->assertNotified();

        expect(User::find($target->id))->not->toBeNull();
    });

    it('does not let a governance-committee member reach the users page at all', function () {
        $governance = User::factory()->create(['role' => 'governance_committee']);

        $this->actingAs($governance)->get('/admin/users')->assertForbidden();
    });
});
