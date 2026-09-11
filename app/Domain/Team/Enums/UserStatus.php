<?php

namespace App\Domain\Team\Enums;

/**
 * Where a trainer account stands. Only the owner moves an account between these —
 * an invited account has no password yet, a blocked one cannot be let back in by a reset.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Blocked = 'blocked';
}
