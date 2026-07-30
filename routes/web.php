<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Marketplace\ListingIndex;
use App\Livewire\Marketplace\ListingShow;
use App\Livewire\Marketplace\CreateListing;
use App\Livewire\Chat\MessageThread;
use App\Livewire\Transactions\Tracker;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DisputeController;

// Public Marketplace Discovery
Route::get('/', ListingIndex::class)->name('listings.index');
Route::get('/listings/{listing}', ListingShow::class)->name('listings.show');

// Auth Routes (Guest Only)
Route::middleware(['guest'])->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

// Authenticated Student Actions
Route::middleware(['auth'])->group(function () {
    // Create Listing Form
    Route::get('/listings-create', CreateListing::class)->name('listings.create');

    // Real-Time Buyer-Seller Chat Flow
    Route::get('/chat/{receiver?}/{listing?}', MessageThread::class)->name('chat.index');
    Route::get('/chat-thread/{receiver}/{listing?}', MessageThread::class)->name('chat.thread');

    // Escrow Transaction Tracker
    Route::get('/transactions-tracker/{transaction?}', Tracker::class)->name('transactions.tracker');

    // Traditional Controller Endpoints
    Route::post('/listings/{listing}/buy', [TransactionController::class, 'initiate'])->name('transactions.initiate');
    Route::post('/transactions/{transaction}/complete', [TransactionController::class, 'complete'])->name('transactions.complete');
    Route::post('/transactions/{transaction}/dispute', [DisputeController::class, 'store'])->name('disputes.store');

    // Logout
    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('listings.index')->with('success', 'You have been logged out.');
    })->name('logout');
});
