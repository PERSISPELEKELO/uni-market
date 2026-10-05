<?php

use App\Livewire\Ratings\VerifyReputation;
use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReputationExporterService;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

function signedExport(array $starsList = [5]): array
{
    $seller = User::factory()->create();

    foreach ($starsList as $stars) {
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
            'comment' => 'Excellent seller.',
        ]);
    }

    return app(ReputationExporterService::class)->exportUserData($seller->fresh());
}

describe('ReputationExporterService::verifyExport', function () {
    beforeEach(fn () => $this->service = app(ReputationExporterService::class));

    it('reports an untampered export as valid', function () {
        $result = $this->service->verifyExport(signedExport());

        expect($result['valid'])->toBeTrue();
    });

    it('rejects an export with an invalid, made-up signature', function () {
        $document = signedExport();
        $document['integrity']['signature'] = base64_encode('not-a-real-signature');

        expect($this->service->verifyExport($document)['valid'])->toBeFalse();
    });

    it('rejects a malformed file with no integrity block at all', function () {
        expect($this->service->verifyExport(['some' => 'garbage'])['valid'])->toBeFalse();
    });

    it('detects a modified rating value', function () {
        $document = signedExport();
        $document['ratings'][0]['stars'] = 1;

        expect($this->service->verifyExport($document)['valid'])->toBeFalse();
    });

    it('detects a modified review/comment text', function () {
        $document = signedExport();
        $document['ratings'][0]['review'] = 'This review text was changed after signing.';

        expect($this->service->verifyExport($document)['valid'])->toBeFalse();
    });

    it('detects a modified transaction amount', function () {
        $document = signedExport();
        $document['transaction_history'][0]['amount'] = 999999.99;

        expect($this->service->verifyExport($document)['valid'])->toBeFalse();
    });

    it('detects a modified student reference', function () {
        $document = signedExport();
        $document['student']['student_reference'] = 'UM-999999';

        expect($this->service->verifyExport($document)['valid'])->toBeFalse();
    });

    it('detects a modified issue date', function () {
        $document = signedExport();
        $document['issued_at'] = now()->addYear()->toIso8601String();

        expect($this->service->verifyExport($document)['valid'])->toBeFalse();
    });

    it('detects a modified reputation summary value (e.g. average_rating inflated)', function () {
        $document = signedExport([5, 3]); // genuine average is 4.0
        $document['reputation']['average_rating'] = 5.0;

        expect($this->service->verifyExport($document)['valid'])->toBeFalse();
    });

    it('detects a modified completed-transaction count', function () {
        $document = signedExport();
        $document['reputation']['completed_transactions'] = 999;

        expect($this->service->verifyExport($document)['valid'])->toBeFalse();
    });

    it('rejects a file claiming a key_id this server does not currently trust', function () {
        $document = signedExport();
        $document['integrity']['key_id'] = '0000000000000000';

        $result = $this->service->verifyExport($document);

        expect($result['valid'])->toBeFalse()
            ->and($result['reason'])->toContain('trusted');
    });
});

describe('the public verify page', function () {
    it('does not require an account to visit or use', function () {
        $this->get(route('reputation.verify'))->assertOk();
    });

    it('reports a genuine, untampered export as a valid reputation record', function () {
        $file = UploadedFile::fake()->createWithContent('reputation_export.json', json_encode(signedExport()));

        Livewire::test(VerifyReputation::class)
            ->set('file', $file)
            ->call('verify')
            ->assertSet('isValid', true);
    });

    it('reports a modified export as invalid', function () {
        $document = signedExport([5, 3]); // genuine average is 4.0
        $document['reputation']['average_rating'] = 5.0;

        $file = UploadedFile::fake()->createWithContent('reputation_export.json', json_encode($document));

        Livewire::test(VerifyReputation::class)
            ->set('file', $file)
            ->call('verify')
            ->assertSet('isValid', false);
    });

    it('reports a non-JSON file as invalid rather than crashing', function () {
        $file = UploadedFile::fake()->createWithContent('not-json.json', 'this is not json');

        Livewire::test(VerifyReputation::class)
            ->set('file', $file)
            ->call('verify')
            ->assertSet('isValid', false);
    });
});

describe('the JSON verify API', function () {
    it('is reachable without authentication', function () {
        $document = signedExport();

        $this->post('/api/reputation/verify', [
            'file' => UploadedFile::fake()->createWithContent('export.json', json_encode($document)),
        ])->assertOk()->assertJsonPath('valid', true);
    });

    it('flags a tampered upload as invalid via the API', function () {
        $document = signedExport();
        $document['ratings'][0]['stars'] = 1;

        $this->post('/api/v1/reputation/verify', [
            'file' => UploadedFile::fake()->createWithContent('export.json', json_encode($document)),
        ])->assertOk()->assertJsonPath('valid', false);
    });
});
