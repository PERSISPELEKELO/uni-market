<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * year_of_study is never typed by a student with an intake_year set - it's
 * computed from intake_year (see User::currentYearOfStudy()) and kept in
 * the column so every existing Insights query that groups by year_of_study
 * keeps working unchanged. Saving the profile re-syncs it immediately; this
 * command re-syncs everyone else so the value keeps advancing as calendar
 * time passes between profile edits.
 */
class RecomputeYearOfStudy extends Command
{
    protected $signature = 'users:recompute-year-of-study';

    protected $description = "Recalculate every user's year_of_study from their intake_year";

    public function handle(): int
    {
        $updated = 0;

        User::query()->whereNotNull('intake_year')->each(function (User $user) use (&$updated) {
            $current = $user->currentYearOfStudy();

            if ($current !== $user->year_of_study) {
                $user->forceFill(['year_of_study' => $current])->save();
                $updated++;
            }
        });

        $this->info("Recomputed year_of_study for {$updated} user(s) with an intake year.");

        return self::SUCCESS;
    }
}
