<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AppealController;
use App\Http\Controllers\Api\GovernanceTransparencyController;
use App\Http\Controllers\Api\TransparencyController;
use App\Http\Controllers\DataPortabilityController;
use Illuminate\Support\Facades\Route;

// Public Governance & Transparency Metrics
Route::get('/v1/transparency/metrics', [TransparencyController::class, 'metrics'])->name('api.transparency.metrics');

// Legacy & Additional Governance Transparency Endpoints
Route::prefix('v1/governance')->group(function () {
    Route::get('/transparency-summary', [GovernanceTransparencyController::class, 'summary'])->name('api.governance.summary');
    Route::get('/audit-feed', [GovernanceTransparencyController::class, 'auditFeed'])->name('api.governance.audit-feed');
});

Route::post('/reputation/verify', [DataPortabilityController::class, 'verify'])->name('api.reputation.verify');
Route::post('/v1/reputation/verify', [DataPortabilityController::class, 'verify'])->name('api.v1.reputation.verify');

// Authenticated Endpoints
Route::middleware(['auth'])->group(function () {
    Route::get('/reputation/export', [DataPortabilityController::class, 'export'])->name('api.reputation.export');

    Route::prefix('v1')->group(function () {
        Route::post('/appeals', [AppealController::class, 'store'])->name('api.v1.appeals.store');
        Route::get('/reputation/export', [DataPortabilityController::class, 'export'])->name('api.v1.reputation.export');

        Route::prefix('governance')->group(function () {
            Route::get('/appeals', [AppealController::class, 'index'])->name('api.v1.governance.appeals.index');
            Route::post('/appeals/{appeal}/resolve', [AppealController::class, 'resolve'])->name('api.v1.governance.appeals.resolve');
        });
    });
});
