<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Support\Money;

/**
 * Edits a card and says in the log what exactly changed — with rates, the amount before and
 * after. Rates are agreed by hand, so "I never changed that" has to be answerable.
 */
class UpdateClient
{
    /** Fields worth naming in the log, in the order they appear in the dialog. */
    private const array LABELS = [
        'name' => 'nazwisko',
        'phone' => 'telefon',
        'email' => 'e-mail',
        'goal' => 'cel',
        'contraindications' => 'przeciwwskazania',
        'guardian' => 'opiekun',
        'trainer_notes' => 'notatka',
        'company_name' => 'dane firmy',
        'tax_id' => 'NIP',
        'consent_given' => 'zgoda RODO',
    ];

    public function __construct(private readonly ActivityLogger $log) {}

    /**
     * @param  array<string, mixed>  $attributes  the same keys CreateClient takes
     */
    public function handle(User $actor, Client $client, array $attributes): Client
    {
        // Values are read before the change rather than through isDirty(): `contraindications`
        // is encrypted, and two encryptions of the same text are two different strings.
        $before = $this->snapshot($client);

        $consent = (bool) ($attributes['consent_given'] ?? false);

        $client->fill([
            ...$attributes,
            'consent_given' => $consent,
            'consent_date' => $consent ? ($client->consent_date?->toDateString() ?? now()->toDateString()) : null,
        ])->save();

        if (filled($client->guardian) && ! $client->tags()->where('label', 'Zgoda rodzica')->exists()) {
            $client->tags()->create(['label' => 'Zgoda rodzica', 'variant' => 'outline']);
        }

        $changes = $this->changes($before, $client);

        $this->log->record(
            $actor,
            'Edytował kartę klienta',
            $client->name.' · '.($changes === [] ? 'bez zmian' : implode(', ', $changes)),
        );

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Client $client): array
    {
        $values = ['rate' => $client->rate];

        foreach (array_keys(self::LABELS) as $field) {
            $values[$field] = $client->{$field};
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $before
     * @return list<string>
     */
    private function changes(array $before, Client $client): array
    {
        $changes = [];

        foreach (self::LABELS as $field => $label) {
            if ((string) $before[$field] !== (string) $client->{$field}) {
                $changes[] = $label;
            }
        }

        if ($before['rate'] !== $client->rate) {
            $changes[] = 'stawka '.Money::format($before['rate']).' → '.Money::format($client->rate);
        }

        return $changes;
    }
}
