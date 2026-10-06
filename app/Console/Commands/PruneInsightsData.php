<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ListingView;
use App\Models\SearchLog;
use Illuminate\Console\Command;

class PruneInsightsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insights:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete listing views and search logs older than 12 months';

    public function handle(): int
    {
        $cutoff = now()->subMonths(12);

        $views = ListingView::where('viewed_at', '<', $cutoff)->delete();
        $searches = SearchLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$views} listing view(s) and {$searches} search log(s) older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
