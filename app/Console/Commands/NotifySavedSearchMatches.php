<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SavedSearch;
use App\Notifications\SavedSearchMatchFound;
use Illuminate\Console\Command;

class NotifySavedSearchMatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saved-searches:notify-matches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify students when new listings match one of their saved searches';

    public function handle(): int
    {
        $count = 0;

        SavedSearch::query()
            ->with('user')
            ->chunkById(200, function ($savedSearches) use (&$count) {
                foreach ($savedSearches as $savedSearch) {
                    $matches = $savedSearch->newMatches()->get();

                    if ($matches->isEmpty()) {
                        continue;
                    }

                    $savedSearch->user->notify(new SavedSearchMatchFound($savedSearch, $matches));
                    $savedSearch->update(['last_notified_at' => now()]);
                    $count++;
                }
            });

        $this->info("Notified {$count} student(s) of new saved-search matches.");

        return self::SUCCESS;
    }
}
