<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\SavedListing;
use App\Notifications\SavedListingPriceDropped;
use Illuminate\Console\Command;

class NotifySavedListingPriceDrops extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saved-listings:notify-price-drops';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify students once when a listing they saved genuinely drops in price';

    public function handle(): int
    {
        $count = 0;

        SavedListing::query()
            ->whereNull('price_drop_notified_at')
            ->whereHas('listing', fn ($query) => $query->where('status', Listing::STATUS_ACTIVE))
            ->with(['user', 'listing'])
            ->chunkById(200, function ($savedListings) use (&$count) {
                foreach ($savedListings as $savedListing) {
                    if ((float) $savedListing->listing->price >= (float) $savedListing->price_at_save) {
                        continue;
                    }

                    $savedListing->user->notify(new SavedListingPriceDropped($savedListing));
                    $savedListing->update(['price_drop_notified_at' => now()]);
                    $count++;
                }
            });

        $this->info("Notified {$count} student(s) of a price drop on a saved listing.");

        return self::SUCCESS;
    }
}
