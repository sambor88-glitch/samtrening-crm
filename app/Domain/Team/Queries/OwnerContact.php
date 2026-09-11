<?php

namespace App\Domain\Team\Queries;

use App\Domain\Team\Models\User;
use Illuminate\Support\Str;

/**
 * The line that closes every access screen: who to ask when you cannot get in. Creating,
 * unblocking and resetting an account is the owner's alone, so it is always this person.
 * Falls back to config/studio.php before the owner account is seeded.
 */
class OwnerContact
{
    public function line(): string
    {
        $owner = User::query()->where('is_owner', true)->first(['name', 'email']);

        return collect([
            Str::before($owner?->name ?? config('studio.owner.name'), ' '),
            $owner?->email ?? config('studio.owner.email'),
            config('studio.owner.phone'),
        ])->filter()->implode(' · ');
    }
}
