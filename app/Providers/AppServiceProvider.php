<?php

namespace App\Providers;

use App\Models\Appeal;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Message;
use App\Models\StudentVerificationDocument;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\AppealPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ListingPolicy;
use App\Policies\StudentVerificationDocumentPolicy;
use App\Policies\TransactionPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Gate::policy(Listing::class, ListingPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(StudentVerificationDocument::class, StudentVerificationDocumentPolicy::class);

        Gate::define('access-governance', fn (User $user) => $user->isGovernanceCommittee() || $user->isAdmin());
        Gate::define('manage-appeals', fn (User $user) => $user->isGovernanceCommittee() || $user->isAdmin());
        Gate::define('verify-audit-chain', fn (User $user) => $user->isGovernanceCommittee() || $user->isAdmin());

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        ViewFacade::composer('layouts.app', function (View $view): void {
            $view->with('unreadMessageCount', $this->unreadMessageCount());
        });
    }

    /**
     * Unread messages for the signed-in user. Falls back to zero so a database
     * problem can never stop the error pages themselves from rendering.
     */
    private function unreadMessageCount(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        try {
            return Message::where('receiver_id', auth()->id())->where('is_read', false)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
