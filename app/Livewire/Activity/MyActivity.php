<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Models\Transaction;
use App\Services\Insights\PeriodBoundary;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The single hub for "things I'm doing on the marketplace" - merges what
 * used to be two separate nav destinations (My listings, My transactions)
 * into one page with tabs, plus a new Insights tab. Deliberately does not
 * rebuild Tracker or MyListings: each tab embeds the existing, already
 *-tested component unchanged, so nothing about how they work changes here.
 */
class MyActivity extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'buying';

    #[Url(as: 'view')]
    public string $insightsView = 'buyer';

    #[Url(as: 'period')]
    public string $insightsPeriod = 'all';

    /**
     * Optional deep-link param, mirroring the existing /transactions-tracker/
     * {transaction} route, so a future link into this hub can still open one
     * specific transaction directly on the Buying tab.
     */
    public ?int $transaction = null;

    public function mount(?int $transaction = null): void
    {
        $this->tab = in_array($this->tab, ['buying', 'selling', 'insights'], true) ? $this->tab : 'buying';
        $this->insightsView = in_array($this->insightsView, ['buyer', 'seller'], true) ? $this->insightsView : 'buyer';
        $this->insightsPeriod = array_key_exists($this->insightsPeriod, PeriodBoundary::OPTIONS) ? $this->insightsPeriod : 'all';

        if ($transaction !== null) {
            $this->tab = 'buying';
            $this->transaction = $transaction;
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['buying', 'selling', 'insights'], true)) {
            $this->tab = $tab;
        }
    }

    public function setInsightsView(string $view): void
    {
        if (in_array($view, ['buyer', 'seller'], true)) {
            $this->insightsView = $view;
        }
    }

    public function setInsightsPeriod(string $period): void
    {
        if (array_key_exists($period, PeriodBoundary::OPTIONS)) {
            $this->insightsPeriod = $period;
        }
    }

    public function render()
    {
        return view('livewire.activity.my-activity', [
            'reservationsReceived' => $this->tab === 'selling' ? $this->reservationsReceived() : collect(),
            'resolvedTransaction' => $this->resolvedTransaction(),
            'periodOptions' => PeriodBoundary::OPTIONS,
        ])->layout('layouts.app', ['title' => 'My Activity - UniMarket']);
    }

    /**
     * Tracker::mount() expects a hydrated Transaction (as Laravel's route
     * model binding would supply it on the standalone tracker route), not a
     * raw id, since this is a nested @livewire embed rather than a route.
     */
    private function resolvedTransaction(): ?Transaction
    {
        return $this->transaction ? Transaction::find($this->transaction) : null;
    }

    private function reservationsReceived()
    {
        return Auth::user()->listings()
            ->with(['activeReservations.buyer'])
            ->whereHas('reservations', fn ($q) => $q->active())
            ->get();
    }
}
