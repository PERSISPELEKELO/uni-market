<?php

use App\Livewire\Transactions\Tracker;
use App\Models\Dispute;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DisputeAnalysisService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    config(['services.dispute_ai.enabled' => true, 'services.dispute_ai.url' => 'http://ai.test']);
});

function inspectionTransaction(): array
{
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $listing = Listing::factory()->pending()->create(['user_id' => $seller->id]);
    $transaction = Transaction::factory()->inInspection()->create([
        'listing_id' => $listing->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
    ]);

    return [$transaction, $buyer];
}

it('stores the AI analysis when the microservice answers', function () {
    Http::fake(['ai.test/*' => Http::response([
        'sentiment_score' => -0.72,
        'confidence_score' => 0.91,
        'suggested_resolution' => 'PARTIAL_REFUND_OR_RETURN',
        'summary' => 'Buyer reports an undisclosed defect.',
    ])]);

    [$transaction, $buyer] = inspectionTransaction();

    $analysis = app(DisputeAnalysisService::class)->analyze($transaction, 'The fan is very loud and dented.');

    expect($analysis)->toBe([
        'ai_sentiment_score' => -0.72,
        'ai_confidence_score' => 0.91,
        'ai_suggested_resolution' => 'PARTIAL_REFUND_OR_RETURN',
        'ai_analysis_summary' => 'Buyer reports an undisclosed defect.',
    ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://ai.test/api/analyze-dispute'
        && $request['transaction_id'] === $transaction->id
        && $request['dispute_reason'] === 'The fan is very loud and dented.'
        && ! isset($request['buyer_id']));
});

it('attaches the analysis to disputes raised from the tracker page', function () {
    Http::fake(['ai.test/*' => Http::response(['sentiment_score' => -0.4, 'confidence_score' => 0.8, 'suggested_resolution' => 'RETURN', 'summary' => 'Item not as described.'])]);

    [$transaction, $buyer] = inspectionTransaction();

    Livewire::actingAs($buyer)->test(Tracker::class, ['transaction' => $transaction])
        ->call('openDisputeModal')
        ->set('disputeReason', 'The screen has a crack across it.')
        ->call('submitDispute')
        ->assertHasNoErrors();

    $dispute = Dispute::firstOrFail();

    expect($transaction->fresh()->status)->toBe('DISPUTED')
        ->and($dispute->ai_sentiment_score)->toBe(-0.4)
        ->and($dispute->ai_suggested_resolution)->toBe('RETURN');
});

it('attaches the analysis to disputes raised through the web endpoint', function () {
    Http::fake(['ai.test/*' => Http::response(['sentiment_score' => -0.9, 'confidence_score' => 0.95, 'suggested_resolution' => 'REFUND', 'summary' => 'Serious defect.'])]);

    [$transaction, $buyer] = inspectionTransaction();

    $this->actingAs($buyer)->post(route('disputes.store', $transaction), ['reason' => 'It does not switch on at all.'])
        ->assertSessionHas('success', 'Dispute raised. AI analysis is attached and a moderator will review it.');

    expect(Dispute::firstOrFail()->ai_confidence_score)->toBe(0.95);
});

it('still records the dispute when the AI service is offline', function () {
    Http::fake(fn () => throw new ConnectionException('connection refused'));

    [$transaction, $buyer] = inspectionTransaction();

    Livewire::actingAs($buyer)->test(Tracker::class, ['transaction' => $transaction])
        ->call('openDisputeModal')
        ->set('disputeReason', 'The screen has a crack across it.')
        ->call('submitDispute')
        ->assertHasNoErrors()
        ->assertSee('Analysis is not available yet');

    $dispute = Dispute::firstOrFail();

    expect($transaction->fresh()->status)->toBe('DISPUTED')
        ->and($dispute->ai_sentiment_score)->toBeNull()
        ->and($dispute->status)->toBe('open');
});

it('ignores an AI answer that is an error, malformed or out of range', function (mixed $response) {
    Http::fake(['ai.test/*' => $response]);

    [$transaction] = inspectionTransaction();

    expect(app(DisputeAnalysisService::class)->analyze($transaction, 'Some dispute reason.'))
        ->toBe(['ai_sentiment_score' => null, 'ai_confidence_score' => null, 'ai_suggested_resolution' => null, 'ai_analysis_summary' => null]);
})->with([
    'server error' => fn () => Http::response('boom', 500),
    'not json' => fn () => Http::response('<html>hi</html>', 200),
    'sentiment above range' => fn () => Http::response(['sentiment_score' => 7, 'confidence_score' => 0.5]),
    'confidence negative' => fn () => Http::response(['sentiment_score' => 0.1, 'confidence_score' => -1]),
    'scores missing' => fn () => Http::response(['summary' => 'x']),
]);

it('strips markup from AI text before it is stored', function () {
    Http::fake(['ai.test/*' => Http::response(['sentiment_score' => 0, 'confidence_score' => 1, 'suggested_resolution' => '<b>REFUND</b>', 'summary' => '<script>alert(1)</script>Fine'])]);

    [$transaction] = inspectionTransaction();

    $analysis = app(DisputeAnalysisService::class)->analyze($transaction, 'Some dispute reason.');

    expect($analysis['ai_suggested_resolution'])->toBe('REFUND')
        ->and($analysis['ai_analysis_summary'])->not->toContain('<script>');
});

it('never calls the microservice when AI moderation is disabled', function () {
    config(['services.dispute_ai.enabled' => false]);
    Http::fake();

    [$transaction] = inspectionTransaction();

    expect(app(DisputeAnalysisService::class)->analyze($transaction, 'Some dispute reason.')['ai_sentiment_score'])->toBeNull();

    Http::assertNothingSent();
});

describe('api authentication', function () {
    it('returns a JSON 401 to callers without a session', function () {
        $this->getJson('/api/v1/reputation/export')->assertUnauthorized()->assertJsonStructure(['message']);
    });

    it('accepts the signed-in browser session, not just test helpers', function () {
        $user = User::factory()->create();

        $this->withSession([Auth::guard('web')->getName() => $user->id])
            ->getJson('/api/v1/reputation/export')
            ->assertOk();
    });

    it('keeps the public transparency endpoints open', function () {
        $this->getJson('/api/v1/transparency/metrics')->assertOk();
    });
});
