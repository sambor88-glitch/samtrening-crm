<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Team\Models\User;

/**
 * Writes one line of the activity log. Called from inside actions — never from Livewire
 * components — so an action run from the console or a queue still leaves a trace.
 */
class ActivityLogger
{
    /**
     * @param  User|null  $actor  null when the system acts on its own, e.g. the daily reminder run
     * @param  string|null  $actorName  what to call a non-human actor; ignored when `$actor` is a
     *                                  person, because an account's own name is the truth. Without
     *                                  it the system acts under "System" as before — but a token
     *                                  acting for somebody deserves to be named, since "who put
     *                                  this here" is the first question about an amount that looks
     *                                  wrong (SC-66).
     */
    public function record(
        ?User $actor,
        string $action,
        ?string $context = null,
        ?string $actorName = null,
    ): ActivityEntry {
        return ActivityEntry::query()->create([
            'user_id' => $actor?->id,
            'actor_name' => $actor?->name ?? $actorName ?? 'System',
            'action' => $action,
            'context' => $context === null ? null : mb_substr($context, 0, 255),
            'happened_at' => now(),
        ]);
    }
}
