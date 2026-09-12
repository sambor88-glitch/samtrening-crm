<?php

use Illuminate\Support\Facades\Schedule;

// docs/START-TUTAJ.md §10. Reminders go out in the morning, when somebody can still answer the
// phone if a client calls back.
Schedule::command('samtrening:monity')
    ->dailyAt('10:00')
    ->timezone(config('app.timezone'));

// Retention runs once a month, at night: it anonymises cards nobody has touched in years, and
// there is no hurry about a deadline measured in years.
Schedule::command('samtrening:retencja')
    ->monthlyOn(1, '03:30')
    ->timezone(config('app.timezone'));
