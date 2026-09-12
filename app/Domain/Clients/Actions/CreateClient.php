<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;

/**
 * Opens a client card. There is no import anywhere in this product, so this is how every
 * client gets in — docs/SPEC-EKRANY.md §Dodaj / edytuj klienta.
 */
class CreateClient
{
    public function __construct(private readonly ActivityLogger $log) {}

    /**
     * @param  array<string, mixed>  $attributes  name, phone, email, rate (grosze), goal,
     *                                            contraindications, guardian, trainer_notes,
     *                                            company_name, tax_id, consent_given
     */
    public function handle(User $trainer, array $attributes): Client
    {
        $consent = (bool) ($attributes['consent_given'] ?? false);

        $client = $trainer->clients()->create([
            ...$attributes,
            'consent_given' => $consent,
            'consent_date' => $consent ? now()->toDateString() : null,
        ]);

        // A guardian means a minor: the card says so at a glance, on the list and on the card.
        if (filled($client->guardian)) {
            $client->tags()->create(['label' => 'Zgoda rodzica', 'variant' => 'outline']);
        }

        $this->log->record(
            $trainer,
            'Dodał klienta',
            $client->name.($consent ? '' : ' · BRAK ZGODY — uzupełnij przed pierwszą sesją'),
        );

        return $client;
    }
}
