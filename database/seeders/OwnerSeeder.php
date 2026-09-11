<?php

namespace Database\Seeders;

use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OwnerSeeder extends Seeder
{
    /**
     * Create the studio owner — the only account that exists before anyone is invited.
     */
    public function run(): void
    {
        if (User::query()->where('is_owner', true)->exists()) {
            return;
        }

        $password = config('studio.owner.password') ?: Str::password(16);

        (new User([
            'name' => config('studio.owner.name'),
            'email' => config('studio.owner.email'),
            'password' => $password,
        ]))->forceFill([
            'status' => UserStatus::Active,
            'is_owner' => true,
            'email_verified_at' => now(),
        ])->save();

        if (! config('studio.owner.password')) {
            $this->command?->warn("Owner password (shown only once): {$password}");
        }
    }
}
