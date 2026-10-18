<?php

return [

    // A store's latest price older than this is shown as out of date (it may have changed since).
    'stale_after_days' => (int) env('PRICES_STALE_AFTER_DAYS', 60),

];
