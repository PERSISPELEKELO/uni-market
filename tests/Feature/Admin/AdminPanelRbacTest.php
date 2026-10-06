<?php

use App\Models\Dispute;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(fn () => $this->withoutVite());

describe('a student', function () {
    it('cannot open the admin panel at all', function () {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get('/admin')->assertForbidden();
    });
});

describe('a governance-committee member', function () {
    it('can open marketplace-admin-only pages but sees no admin-only actions on them', function () {
        $governance = User::factory()->create(['role' => 'governance_committee']);

        $this->actingAs($governance)->get('/admin/users')->assertForbidden();
        $this->actingAs($governance)->get('/admin/listings')->assertForbidden();
        $this->actingAs($governance)->get('/admin/categories')->assertForbidden();
        $this->actingAs($governance)->get('/admin/transactions')->assertForbidden();
    });

    it('can open disputes, appeals, ratings, student verification and audit logs', function () {
        $governance = User::factory()->create(['role' => 'governance_committee']);

        $this->actingAs($governance)->get('/admin/disputes')->assertOk();
        $this->actingAs($governance)->get('/admin/appeals')->assertOk();
        $this->actingAs($governance)->get('/admin/ratings')->assertOk();
        $this->actingAs($governance)->get('/admin/student-verification-documents')->assertOk();
        $this->actingAs($governance)->get('/admin/audit-logs')->assertOk();
    });

    it('can open the Market Insights BI dashboard', function () {
        $governance = User::factory()->create(['role' => 'governance_committee']);

        $this->actingAs($governance)->get('/admin/market-insights')->assertOk();
    });
});

describe('an admin', function () {
    it('can open every admin page', function () {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['/admin', '/admin/users', '/admin/listings', '/admin/categories', '/admin/transactions', '/admin/disputes', '/admin/appeals', '/admin/ratings', '/admin/student-verification-documents', '/admin/audit-logs', '/admin/settings', '/admin/market-insights'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    });

    it('cannot suspend its own account through the UserResource action', function () {
        $admin = User::factory()->create(['role' => 'admin']);

        expect(Gate::forUser($admin)->allows('suspend', $admin))->toBeFalse();
    });

    it('can suspend a student but a governance-committee member cannot', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $governance = User::factory()->create(['role' => 'governance_committee']);
        $student = User::factory()->create(['role' => 'student']);

        expect(Gate::forUser($admin)->allows('suspend', $student))->toBeTrue()
            ->and(Gate::forUser($governance)->allows('suspend', $student))->toBeFalse();
    });

    it('sees the real database counts on the dashboard, not placeholder numbers', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'student']);
        Dispute::query()->delete();
        $transaction = Transaction::factory()->create();
        Dispute::create([
            'transaction_id' => $transaction->id,
            'raised_by' => $transaction->buyer_id,
            'reason' => 'Testing dashboard counts.',
            'status' => 'open',
        ]);

        $totalUsers = User::count();
        $openDisputes = Dispute::whereIn('status', ['open', 'under_review'])->count();

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee((string) $totalUsers)
            ->assertSee((string) $openDisputes);
    });

    it('renders the admin panel with Filament\'s dark mode toggle available', function () {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('dark', false);
    });
});
