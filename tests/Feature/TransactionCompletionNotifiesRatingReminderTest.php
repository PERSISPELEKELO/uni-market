<?php

use App\Models\Transaction;
use App\Models\User;
use App\Notifications\TransactionCompletedRateReminder;
use App\Services\InspectionService;
use Illuminate\Support\Facades\Notification;

function pendingTransaction(array $overrides = []): Transaction
{
    return Transaction::factory()->create(array_merge([
        'buyer_id' => User::factory(),
        'seller_id' => User::factory(),
        'status' => 'ITEM_INSPECTION',
    ], $overrides));
}

it('notifies both buyer and seller to rate once the buyer confirms item acceptance', function () {
    Notification::fake();
    $transaction = pendingTransaction();

    app(InspectionService::class)->confirmItemAcceptance($transaction, $transaction->buyer);

    Notification::assertSentTo($transaction->buyer, TransactionCompletedRateReminder::class);
    Notification::assertSentTo($transaction->seller, TransactionCompletedRateReminder::class);
});

it('does not fire the rating reminder for updates that are not a completion', function () {
    Notification::fake();
    $transaction = pendingTransaction();

    $transaction->update(['status' => 'HANDED_OVER']);

    Notification::assertNothingSent();
});

it('does not fire the rating reminder when a transaction is simply created as already completed', function () {
    Notification::fake();

    Transaction::factory()->create([
        'buyer_id' => User::factory(),
        'seller_id' => User::factory(),
        'status' => 'COMPLETED',
    ]);

    Notification::assertNothingSent();
});
