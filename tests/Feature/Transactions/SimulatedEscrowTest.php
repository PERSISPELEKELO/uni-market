<?php

use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InspectionService;
use App\Services\ReservationService;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('simulates a held payment the instant a buyer is selected, with no extra buyer step', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->create(['user_id' => $seller->id, 'price' => 250]);
    $reservation = Reservation::factory()->for($listing)->create();

    $transaction = app(ReservationService::class)->selectBuyer($reservation, $seller);

    expect($transaction->payment_status)->toBe('HELD')
        ->and($transaction->funds_held_at)->not->toBeNull()
        ->and($transaction->isFundsHeld())->toBeTrue();
});

it('releases the simulated funds when the buyer confirms item acceptance', function () {
    $buyer = User::factory()->create();
    $transaction = Transaction::factory()->inInspection()->create(['buyer_id' => $buyer->id]);

    app(InspectionService::class)->confirmItemAcceptance($transaction, $buyer);

    $transaction->refresh();
    expect($transaction->payment_status)->toBe('RELEASED')
        ->and($transaction->funds_released_at)->not->toBeNull()
        ->and($transaction->isFundsReleased())->toBeTrue();
});

it('releases the simulated funds on a completed direct-mode purchase', function () {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create(['category_id' => \App\Models\Category::factory()->create(['transaction_mode' => 'DIRECT'])->id]);
    $transaction = Transaction::factory()->create([
        'buyer_id' => $buyer->id,
        'listing_id' => $listing->id,
        'transaction_mode' => 'DIRECT',
        'status' => 'PENDING_MEETING',
    ]);

    app(InspectionService::class)->completeDirectPurchase($transaction, $buyer);

    expect($transaction->fresh()->payment_status)->toBe('RELEASED');
});

it('shows the simulated payment badge on the tracker and hub, clearly labelled as simulated', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $transaction = Transaction::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'payment_status' => 'HELD']);

    $this->actingAs($buyer)->get(route('transactions.tracker', $transaction))
        ->assertSee('Held in escrow')
        ->assertSee('(simulated)');

    $this->actingAs($buyer)->get(route('transactions.index'))
        ->assertSee('Held in escrow')
        ->assertSee('(simulated)');
});

describe('admin dispute resolution moves the simulated funds', function () {
    it('marks the transaction refunded when the admin resolves in the buyer\'s favour', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create();
        $transaction = Transaction::factory()->create(['buyer_id' => $buyer->id, 'payment_status' => 'HELD']);
        $dispute = \App\Models\Dispute::create(['transaction_id' => $transaction->id, 'raised_by' => $buyer->id, 'reason' => 'Item not as described.', 'status' => 'open']);

        Livewire::actingAs($admin)->test(\App\Filament\Resources\DisputeResource\Pages\ListDisputes::class)
            ->callTableAction('resolve_buyer', $dispute);

        $transaction->refresh();
        expect($transaction->payment_status)->toBe('REFUNDED')
            ->and($transaction->refunded_at)->not->toBeNull()
            ->and($dispute->fresh()->status)->toBe('resolved_buyer');
    });

    it('marks the transaction released when the admin resolves in the seller\'s favour', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create();
        $transaction = Transaction::factory()->create(['buyer_id' => $buyer->id, 'payment_status' => 'HELD']);
        $dispute = \App\Models\Dispute::create(['transaction_id' => $transaction->id, 'raised_by' => $buyer->id, 'reason' => 'Item not as described.', 'status' => 'open']);

        Livewire::actingAs($admin)->test(\App\Filament\Resources\DisputeResource\Pages\ListDisputes::class)
            ->callTableAction('resolve_seller', $dispute);

        $transaction->refresh();
        expect($transaction->payment_status)->toBe('RELEASED')
            ->and($transaction->funds_released_at)->not->toBeNull();
    });

    it('hides both resolution actions once a dispute is already resolved, so funds can never move twice', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create();
        $transaction = Transaction::factory()->create(['buyer_id' => $buyer->id, 'payment_status' => 'RELEASED']);
        $dispute = \App\Models\Dispute::create(['transaction_id' => $transaction->id, 'raised_by' => $buyer->id, 'reason' => 'x', 'status' => 'resolved_seller']);

        Livewire::actingAs($admin)->test(\App\Filament\Resources\DisputeResource\Pages\ListDisputes::class)
            ->assertTableActionHidden('resolve_buyer', $dispute)
            ->assertTableActionHidden('resolve_seller', $dispute);
    });
});
