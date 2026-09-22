<?php

use App\Http\Controllers\Account\VerificationDocumentController;
use App\Http\Controllers\AppealController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DataPortabilityController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\TransactionController;
use App\Livewire\Account\Profile;
use App\Livewire\Account\StudentVerification;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use App\Livewire\Chat\MessageThread;
use App\Livewire\Marketplace\CreateListing;
use App\Livewire\Marketplace\EditListing;
use App\Livewire\Marketplace\ListingIndex;
use App\Livewire\Marketplace\ListingShow;
use App\Livewire\Marketplace\MyListings;
use App\Livewire\Profiles\PublicProfile;
use App\Livewire\Transactions\Tracker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Public Marketplace Discovery
Route::get('/', ListingIndex::class)->name('listings.index');
Route::get('/listings/{listing}', ListingShow::class)->name('listings.show');
Route::get('/students/{user}', PublicProfile::class)->name('profiles.show');

// Auth Routes (Guest Only)
Route::middleware(['guest'])->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

// Authenticated Student Actions
Route::middleware(['auth'])->group(function () {
    // Account, email verification and student identity verification
    Route::get('/account', Profile::class)->name('account');
    Route::get('/email/verify', VerifyEmail::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::get('/account/student-verification', StudentVerification::class)->name('verification.student.form');
    Route::get('/account/verification-documents/{document}', [VerificationDocumentController::class, 'show'])
        ->name('verification.documents.show');

    // Seller listing management
    Route::get('/listings-create', CreateListing::class)->name('listings.create');
    Route::get('/my-listings', MyListings::class)->name('listings.mine');
    Route::get('/listings/{listing}/edit', EditListing::class)->name('listings.edit');

    // Real-Time Buyer-Seller Chat Flow
    Route::get('/chat/{receiver?}/{listing?}', MessageThread::class)->name('chat.index');
    Route::get('/chat-thread/{receiver}/{listing?}', MessageThread::class)->name('chat.thread');

    // Escrow Transaction Tracker
    Route::get('/transactions-tracker/{transaction?}', Tracker::class)->name('transactions.tracker');

    // Traditional Controller Endpoints
    Route::post('/transactions/{transaction}/verify-handover', [TransactionController::class, 'verifyHandover'])->name('transactions.verify-handover');
    Route::post('/transactions/{transaction}/complete', [TransactionController::class, 'complete'])->name('transactions.complete');
    Route::post('/transactions/{transaction}/dispute', [DisputeController::class, 'store'])->name('disputes.store');

    // User Appeals Routes
    Route::get('/appeals', [AppealController::class, 'index'])->name('appeals.index');
    Route::post('/appeals', [AppealController::class, 'store'])->name('appeals.store');
    Route::get('/appeals/{appeal}', [AppealController::class, 'show'])->name('appeals.show');
    Route::post('/appeals/{appeal}/review', [AppealController::class, 'startReview'])->name('appeals.review');
    Route::post('/appeals/{appeal}/decide', [AppealController::class, 'decide'])->name('appeals.decide');

    // Data Portability Exporter
    Route::get('/reputation/export', [DataPortabilityController::class, 'export'])->name('reputation.export');
    Route::post('/reputation/verify', [DataPortabilityController::class, 'verify'])->name('reputation.verify');

    // Logout
    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('listings.index')->with('status', 'You have been logged out.');
    })->name('logout');
});
