<?php

namespace App\Domain\Team\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Blocking is the studio's stop button. The owner's own account is not blockable — there would be
 * nobody left to undo it — and the rule lives here, not in a hidden button, so calling the action
 * directly cannot go around it.
 */
class BlockTrainer
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(User $owner, User $trainer, bool $blocked): User
    {
        if ($blocked && $trainer->is_owner) {
            throw new RuntimeException('Konta właściciela nie da się zablokować.');
        }

        if ($trainer->status === UserStatus::Invited) {
            throw new RuntimeException('Zaproszone konto nie ma czego blokować — najpierw musi zostać aktywowane.');
        }

        $trainer->forceFill(['status' => $blocked ? UserStatus::Blocked : UserStatus::Active])->save();

        // Out of every browser, not just the next one: with the database session driver the rows
        // are the sessions. The `active` middleware catches anything this misses.
        if ($blocked) {
            DB::table('sessions')->where('user_id', $trainer->getKey())->delete();
        }

        $this->log->record(
            $owner,
            $blocked ? 'Zablokował trenera' : 'Odblokował trenera',
            $trainer->name.' · '.$trainer->email,
        );

        return $trainer;
    }
}
