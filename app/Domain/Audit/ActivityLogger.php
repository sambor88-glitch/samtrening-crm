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
     */
    public function record(?User $actor, string $action, ?string $context = null): ActivityEntry
    {
        return ActivityEntry::query()->create([
            'user_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'System',
            'action' => $action,
            'context' => $context === null ? null : mb_substr($context, 0, 255),
            'happened_at' => now(),
        ]);
    }
}
