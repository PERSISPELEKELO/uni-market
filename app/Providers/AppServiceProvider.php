<?php

namespace App\Providers;

use App\Models\Appeal;
use App\Models\AuditLog;
use App\Models\User;
use App\Policies\AppealPolicy;
use App\Policies\AuditLogPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Appeal::class, AppealPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        Gate::define('access-governance', fn (User $user) => $user->isGovernanceCommittee() || $user->isAdmin());
        Gate::define('manage-appeals', fn (User $user) => $user->isGovernanceCommittee() || $user->isAdmin());
        Gate::define('verify-audit-chain', fn (User $user) => $user->isGovernanceCommittee() || $user->isAdmin());
    }
}

