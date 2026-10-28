<?php

declare(strict_types=1);

namespace App\Livewire\Transactions;

use App\Services\TransactionInboxService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Lists every transaction the signed-in user takes part in (as buyer or
 * seller), grouped into tabs - a dashboard only. Reuses Tracker for every
 * actual action (entering a code, confirming, disputing, rating): each
 * card's button just links to transactions.tracker for that transaction.
 */
class TransactionsHub extends Component
{
    #[Url(as: 'tab')]
    public string $activeTab = 'needs_action';

    private const TABS = ['needs_action', 'in_progress', 'completed', 'disputed'];

    public function mount(): void
    {
        if (! in_array($this->activeTab, self::TABS, true)) {
            $this->activeTab = 'needs_action';
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, self::TABS, true)) {
            $this->activeTab = $tab;
        }
    }

    public function render()
    {
        $grouped = app(TransactionInboxService::class)->grouped(Auth::user());

        return view('livewire.transactions.transactions-hub', [
            'grouped' => $grouped,
            'counts' => array_map(fn ($items) => $items->count(), $grouped),
        ])->layout('layouts.app', ['title' => 'My Transactions - UniMarket']);
    }
}
