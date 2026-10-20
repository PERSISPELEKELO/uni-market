<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('transactions:auto-complete')->hourly();
Schedule::command('insights:prune')->daily();
Schedule::command('users:recompute-year-of-study')->daily();
Schedule::command('recommendations:refresh-similarity')->daily();
Schedule::command('saved-listings:notify-price-drops')->daily();
Schedule::command('saved-searches:notify-matches')->daily();
Schedule::command('verification:prune-documents')->daily();
