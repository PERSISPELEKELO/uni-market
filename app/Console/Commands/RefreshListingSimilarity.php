<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Recommendations\ContentSimilarityService;
use Illuminate\Console\Command;

/**
 * Nightly full recompute of every active listing's "Similar items" list.
 * A single listing also gets refreshed immediately when created or edited
 * (see ContentSimilarityService::refreshFor(), called from ListingForm) -
 * this command is what keeps every OTHER listing's list current as the
 * rest of the catalogue changes around it.
 */
class RefreshListingSimilarity extends Command
{
    protected $signature = 'recommendations:refresh-similarity';

    protected $description = "Recompute every active listing's content-based similarity list";

    public function handle(ContentSimilarityService $similarity): int
    {
        $similarity->refreshAll();

        $this->info('Listing similarity recomputed.');

        return self::SUCCESS;
    }
}
