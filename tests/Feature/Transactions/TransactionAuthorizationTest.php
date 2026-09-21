<?php

use App\Livewire\Transactions\Tracker;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

function makeTransaction(string $state = 'meeting'): array
{
    $buyer = User::factory()->create(['name' => 'Bella Buyer']);
    $seller = User::factory()->create(['name' => 'Sam Seller']);
    $outsider = User::factory()->create(['name' => 'Olive Outsider']);
    $listing = Listing::factory()->pending()->create(['user_id' => $seller->id, 'title' => 'Secret handover item']);

    $transaction = Transaction::factory()
        ->when($state === 'inspection', fn ($factory) => $factory->inInspection())
        ->create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'handover_otp_plain' => '123456',
            'handover_code_plain' => '123456',
            'handover_otp_hash' => bcrypt('123456'),
            'handover_code_hash' => bcrypt('123456'),
            'handover_code_expires_at' => now()->addDay(),
        ]);

    return [$transaction, $buyer, $seller, $outsider, $listing];
}

describe('viewing a transaction', function () {
    it('shows the transaction and handover code to the buyer', function () {
        [$transaction, $buyer] = makeTransaction();

        $this->actingAs($buyer)->get(route('transactions.tracker', $transaction))
            ->assertOk()
            ->assertSee('Secret handover item')
            ->assertSee('123456');
    });

    it('never shows the handover code to the seller', function () {
        [$transaction, , $seller] = makeTransaction();

        $this->actingAs($seller)->get(route('transactions.tracker', $transaction))
            ->assertOk()
            ->assertSee('Secret handover item')
            ->assertDontSee('123456');
    });

    it('returns 404 to a signed-in user who is not part of the transaction', function () {
        [$transaction, , , $outsider] = makeTransaction();

        $this->actingAs($outsider)->get(route('transactions.tracker', $transaction))
            ->assertNotFound()
            ->assertDontSee('123456')
            ->assertDontSee('Secret handover item');
    });

    it('does not let a user switch the tracker to a transaction they do not own', function () {
        [$transaction, , , $outsider] = makeTransaction();

        Livewire::actingAs($outsider)->test(Tracker::class)
            ->call('selectTransaction', $transaction->id)
            ->assertNotFound()
            ->assertSet('selectedTransactionId', null);
    });

    it('locks the selected transaction id against tampering from the browser', function () {
        [$transaction, , , $outsider] = makeTransaction();

        expect(fn () => Livewire::actingAs($outsider)->test(Tracker::class)->set('selectedTransactionId', $transaction->id))
            ->toThrow('Cannot update locked property');
    });

    it('only lists the user\'s own transactions', function () {
        [, $buyer] = makeTransaction();
        makeTransaction();

        Livewire::actingAs($buyer)->test(Tracker::class)
            ->assertViewHas('transactions', fn ($transactions) => $transactions->count() === 1);
    });
});

describe('completing a transaction', function () {
    it('lets the buyer confirm completion during the inspection window', function () {
        [$transaction, $buyer, , , $listing] = makeTransaction('inspection');

        $this->actingAs($buyer)->post(route('transactions.complete', $transaction))
            ->assertRedirect()
            ->assertSessionHas('success');

        expect($transaction->fresh()->status)->toBe('COMPLETED')
            ->and($listing->fresh()->status)->toBe('sold');
    });

    it('does not let the seller complete a transaction and release the item', function () {
        [$transaction, , $seller] = makeTransaction('inspection');

        $this->actingAs($seller)->post(route('transactions.complete', $transaction))->assertForbidden();

        expect($transaction->fresh()->status)->toBe('ITEM_INSPECTION');
    });

    it('does not let the buyer skip the handover verification', function () {
        [$transaction, $buyer] = makeTransaction('meeting');

        $this->actingAs($buyer)->post(route('transactions.complete', $transaction))->assertForbidden();

        expect($transaction->fresh()->status)->toBe('PENDING_MEETING');
    });

    it('does not let an outsider complete someone else\'s transaction', function () {
        [$transaction, , , $outsider] = makeTransaction('inspection');

        $this->actingAs($outsider)->post(route('transactions.complete', $transaction))->assertForbidden();

        expect($transaction->fresh()->status)->toBe('ITEM_INSPECTION');
    });

    it('ignores a completion attempt from the seller in the live tracker', function () {
        [$transaction, , $seller] = makeTransaction('inspection');

        Livewire::actingAs($seller)->test(Tracker::class, ['transaction' => $transaction])->call('markCompleted');

        expect($transaction->fresh()->status)->toBe('ITEM_INSPECTION');
    });

    it('lets the buyer confirm completion from the live tracker', function () {
        [$transaction, $buyer] = makeTransaction('inspection');

        Livewire::actingAs($buyer)->test(Tracker::class, ['transaction' => $transaction])->call('markCompleted');

        expect($transaction->fresh()->status)->toBe('COMPLETED');
    });
});

describe('handover verification', function () {
    it('does not let a stranger verify a handover code', function () {
        [$transaction, , , $outsider] = makeTransaction();

        $this->actingAs($outsider)->post(route('transactions.verify-handover', $transaction), ['otp' => '123456'])->assertForbidden();

        expect($transaction->fresh()->status)->toBe('PENDING_MEETING');
    });

    it('does not let the buyer verify their own handover', function () {
        [$transaction, $buyer] = makeTransaction();

        $this->actingAs($buyer)->post(route('transactions.verify-handover', $transaction), ['otp' => '123456'])->assertForbidden();

        expect($transaction->fresh()->status)->toBe('PENDING_MEETING');
    });

    it('lets the seller verify the buyer\'s code in the live tracker and validates its format', function () {
        [$transaction, , $seller] = makeTransaction();

        Livewire::actingAs($seller)->test(Tracker::class, ['transaction' => $transaction])
            ->set('handoverOtp', '12')
            ->call('verifyHandoverOtp')
            ->assertHasErrors('handoverOtp')
            ->assertSee('The handover code must be exactly 6 digits.')
            ->set('handoverOtp', '123456')
            ->call('verifyHandoverOtp')
            ->assertHasNoErrors();

        expect($transaction->fresh()->status)->toBe('ITEM_INSPECTION');
    });
});

describe('disputes', function () {
    it('lets the buyer raise a dispute during the inspection window', function () {
        [$transaction, $buyer] = makeTransaction('inspection');

        $this->actingAs($buyer)->post(route('disputes.store', $transaction), ['reason' => 'The screen is cracked and not in the photos.'])
            ->assertRedirect();

        expect($transaction->fresh()->status)->toBe('DISPUTED');
    });

    it('does not let an outsider or the seller raise a dispute on someone else\'s transaction', function () {
        [$transaction, , $seller, $outsider] = makeTransaction('inspection');

        $this->actingAs($outsider)->post(route('disputes.store', $transaction), ['reason' => 'I do not like this seller at all.'])->assertForbidden();
        $this->actingAs($seller)->post(route('disputes.store', $transaction), ['reason' => 'Trying to freeze my own sale.'])->assertForbidden();

        expect($transaction->fresh()->status)->toBe('ITEM_INSPECTION');
    });

    it('validates the dispute reason in the live tracker', function () {
        [$transaction, $buyer] = makeTransaction('inspection');

        Livewire::actingAs($buyer)->test(Tracker::class, ['transaction' => $transaction])
            ->call('openDisputeModal')
            ->assertSet('showDisputeModal', true)
            ->set('disputeReason', 'short')
            ->call('submitDispute')
            ->assertHasErrors('disputeReason')
            ->assertSee('at least 10 characters');
    });
});
