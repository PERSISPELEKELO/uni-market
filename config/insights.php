<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum group size
    |--------------------------------------------------------------------------
    |
    | Any Insights breakdown that describes a group of students other than
    | the signed-in user (e.g. "buyers by year of study") must never be
    | shown, or exported, for a group smaller than this many distinct
    | students - it shows "Not enough data yet" instead. See the
    | Insights services in app/Services/Insights.
    |
    */
    'min_group_size' => (int) env('INSIGHTS_MIN_GROUP_SIZE', 5),

    /*
    |--------------------------------------------------------------------------
    | Cache duration (minutes)
    |--------------------------------------------------------------------------
    |
    | Heavy aggregate queries are cached for this long. Invalidated early
    | whenever a transaction completes (see TransactionObserver).
    |
    */
    'cache_minutes' => 10,

];
