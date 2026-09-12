<?php

namespace App\Domain\Privacy\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Models\ClientFile;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Plural;
use Illuminate\Support\Facades\Storage;

/**
 * The right to erasure, as far as it goes — docs/START-TUTAJ.md §10. Everything personal goes:
 * contact, goal, baseline, health, notes, the plan for next time, tags, files on disk. The name
 * becomes "Dane usunięte #XXXX".
 *
 * The sessions stay, with their notes wiped. The amounts have to match what was declared to the
 * tax office, and a row of money with no row behind it is worse than useless.
 *
 * Irreversible, always logged, and the log keeps the name — it has to say whose data went.
 */
class AnonymizeClient
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(?User $actor, Client $client): Client
    {
        $was = $client->name;

        foreach ($client->files as $file) {
            /** @var ClientFile $file */
            Storage::disk('local')->delete($file->path);
            $file->delete();
        }

        $client->tags()->delete();

        // Session notes are health data too ("kolano, bez skoków"), so they go with the rest —
        // including the notes on deleted sessions, which are still sitting there soft-deleted.
        TrainingSession::withTrashed()->where('client_id', $client->getKey())->update(['notes' => null]);

        $client->forceFill([
            'name' => 'Dane usunięte #'.str_pad((string) $client->getKey(), 4, '0', STR_PAD_LEFT),
            'phone' => null,
            'email' => null,
            'goal' => null,
            'baseline' => null,
            'contraindications' => null,
            'trainer_notes' => null,
            'next_session_plan' => null,
            'guardian' => null,
            'company_name' => null,
            'tax_id' => null,
            'archived' => true,
        ])->save();

        $this->log->record(
            $actor,
            'Usunął dane klienta (RODO)',
            $was.' → '.$client->name.' · '.$this->kept($client),
        );

        return $client;
    }

    private function kept(Client $client): string
    {
        $sessions = TrainingSession::withTrashed()->where('client_id', $client->getKey())->count();

        return $sessions === 0
            ? 'bez historii sesji'
            : Plural::of($sessions, 'sesja', 'sesje', 'sesji').' zostaje bez notatek';
    }
}
