<?php

/*
 * The rules from docs/START-TUTAJ.md §4 that a machine can check. The action namespaces are
 * empty until the first action lands; the rules start biting then.
 */

arch()->preset()->php();

arch()->preset()->security();

arch('domain code does not depend on the web layer')
    ->expect('App\Domain')
    ->not->toUse(['App\Http', 'App\Livewire', 'Livewire']);

arch('support helpers do not depend on the domain')
    ->expect('App\Support')
    ->not->toUse('App\Domain');

arch('an action exposes a single public handle() method (rule 1)')
    ->expect([
        'App\Domain\Clients\Actions',
        'App\Domain\Training\Actions',
        'App\Domain\Billing\Actions',
        'App\Domain\Messaging\Actions',
        'App\Domain\Team\Actions',
        'App\Domain\Privacy\Actions',
    ])
    ->classes()
    ->toHaveMethod('handle')
    ->not->toHavePublicMethodsBesides(['__construct', 'handle']);

/*
 * One namespace per rule. `expect([...])->not->toUse(...)` passes even when a class in the list
 * breaks the rule — this pair was written that way and guarded nothing for five stories. Only
 * that combination is affected: an array on the `toUse` side, and `expect([...])->classes()`
 * below, both bite (checked).
 */
arch('the activity log is written from actions, not from Livewire components (rule 3)')
    ->expect('App\Livewire')
    ->not->toUse('App\Domain\Audit\ActivityLogger');

arch('the activity log is written from actions, not from controllers (rule 3)')
    ->expect('App\Http')
    ->not->toUse('App\Domain\Audit\ActivityLogger');

arch('Livewire components ask Queries/ objects instead of querying (rule 5)')
    ->expect('App\Livewire')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\Query\Builder',
        'Illuminate\Database\Eloquent\Builder',
    ]);
