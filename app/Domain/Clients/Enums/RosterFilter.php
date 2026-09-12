<?php

namespace App\Domain\Clients\Enums;

/**
 * The segmented control above the client list. The values travel in the URL, so they are
 * written the way the rest of the app writes addresses — in Polish.
 */
enum RosterFilter: string
{
    case Active = 'aktywni';
    case Owing = 'zalegli';
    case Online = 'online';
    case Archived = 'archiwum';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktywni',
            self::Owing => 'Z saldem',
            self::Online => 'Online',
            self::Archived => 'Archiwum',
        };
    }

    /**
     * Value => label, the shape the <x-seg> component takes.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $filter) => [$filter->value => $filter->label()])->all();
    }
}
