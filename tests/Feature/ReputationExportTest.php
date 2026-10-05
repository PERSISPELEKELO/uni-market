<?php

use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReputationExporterService;

function exportSubject(): User
{
    $seller = User::factory()->create(['role' => 'student', 'email' => 'export-subject@example.com', 'student_id' => '2024987654', 'phone_number' => '+260970000001']);

    foreach ([5, 4] as $stars) {
        $transaction = Transaction::factory()->create([
            'seller_id' => $seller->id,
            'buyer_id' => User::factory(),
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);
        Rating::create([
            'transaction_id' => $transaction->id,
            'rater_id' => $transaction->buyer_id,
            'rated_id' => $seller->id,
            'stars' => $stars,
            'comment' => "Review for {$stars} stars.",
        ]);
    }

    // A transaction with no rating yet still belongs in the history.
    Transaction::factory()->create(['seller_id' => $seller->id, 'buyer_id' => User::factory(), 'status' => 'COMPLETED', 'completed_at' => now()]);
    // Not completed - must not appear.
    Transaction::factory()->create(['seller_id' => $seller->id, 'buyer_id' => User::factory(), 'status' => 'PENDING_MEETING']);

    return $seller->fresh();
}

describe('the export endpoint', function () {
    it('lets an authenticated student export their own data', function () {
        $user = exportSubject();

        $this->actingAs($user)->get(route('reputation.export'))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="reputation_export_'.now()->format('Y-m-d').'.json"');
    });

    it('redirects an unauthenticated visitor to log in instead of exporting', function () {
        $this->get(route('reputation.export'))->assertRedirect(route('login'));
    });

    it('always exports the authenticated user\'s own data, ignoring anything the request claims otherwise', function () {
        $user = exportSubject();
        $other = User::factory()->create(['name' => 'Someone Else']);

        $document = $this->actingAs($user)->get(route('reputation.export').'?user_id='.$other->id)->json();

        expect($document['student']['name'])->toBe($user->name)
            ->and($document['student']['name'])->not->toBe('Someone Else');
    });

    it('contains the real reputation figures from the same source as the profile page', function () {
        $user = exportSubject();

        $document = app(ReputationExporterService::class)->exportUserData($user);

        expect($document['reputation']['average_rating'])->toBe($user->averageRating())
            ->and($document['reputation']['total_ratings'])->toBe($user->ratingsCount())
            ->and($document['reputation']['completed_transactions'])->toBe($user->completedTransactionsCount())
            ->and($document['reputation']['rating_distribution'])->toBe($user->ratingBreakdown());
    });

    it('contains the real ratings received', function () {
        $user = exportSubject();

        $document = app(ReputationExporterService::class)->exportUserData($user);

        expect($document['ratings'])->toHaveCount(2)
            ->and(collect($document['ratings'])->pluck('stars')->sort()->values()->all())->toBe([4, 5]);
    });

    it('contains the real completed transaction history, excluding transactions that are not completed', function () {
        $user = exportSubject();

        $document = app(ReputationExporterService::class)->exportUserData($user);

        expect($document['transaction_history'])->toHaveCount(3)
            ->and(collect($document['transaction_history'])->every(fn ($tx) => $tx['role'] === 'seller'))->toBeTrue();
    });

    it('is valid JSON containing a non-empty digital signature', function () {
        $user = exportSubject();

        $document = app(ReputationExporterService::class)->exportUserData($user);

        expect($document['integrity']['signature'])->toBeString()->not->toBeEmpty()
            ->and($document['integrity']['signature_algorithm'])->toBe(ReputationExporterService::SIGNATURE_ALGORITHM)
            ->and(json_encode($document))->toBeJson();
    });

    it('never includes the private key, password, or other sensitive account fields', function () {
        $user = exportSubject();

        $document = app(ReputationExporterService::class)->exportUserData($user);
        $raw = json_encode($document);

        expect($raw)
            ->not->toContain('PRIVATE KEY')
            ->not->toContain($user->email)
            ->not->toContain($user->student_id)
            ->not->toContain($user->phone_number)
            ->not->toContain($user->password);
    });

    it('matches exactly what the profile page shows', function () {
        $user = exportSubject();

        $document = app(ReputationExporterService::class)->exportUserData($user);

        $this->get(route('profiles.show', $user))
            ->assertSee(number_format($document['reputation']['average_rating'], 1))
            ->assertSee($document['reputation']['total_ratings'].' ratings')
            ->assertSee($document['reputation']['completed_transactions'].' completed transactions');
    });
});
