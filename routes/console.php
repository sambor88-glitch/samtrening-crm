<?php

use Illuminate\Support\Facades\Schedule;

// The studio's only scheduled job so far — docs/START-TUTAJ.md §10. Reminders go out in the
// morning, when somebody can still answer the phone if a client calls back.
Schedule::command('samtrening:monity')
    ->dailyAt('10:00')
    ->timezone(config('app.timezone'));
