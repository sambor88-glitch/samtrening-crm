<?php

namespace App\Domain\Privacy\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\Balance;
use App\Domain\Billing\Models\Prepayment;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Models\ClientFile;
use App\Domain\Clients\Models\ClientTag;
use App\Domain\Privacy\Export\DataFile;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Everything the studio holds about one person, in a form they can read — the right of access
 * (art. 15 RODO). Written as plain text on purpose: it is handed to a client, not to a system,
 * and a spreadsheet would hide the notes that matter most here.
 *
 * Nothing about anybody else appears in it, which is the other half of the obligation.
 */
class ExportClientData
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly Balance $balance,
    ) {}

    public function handle(User $actor, Client $client): DataFile
    {
        $client->loadMissing('trainer:id,name,email', 'tags', 'files', 'sessions', 'prepayments');

        // One block per section, blank line between them: this is read on paper, not parsed.
        $parts = [
            "DANE KLIENTA — SAMtrening CRM\nWygenerowano: ".now()->format('d.m.Y H:i')."\nWydał: ".$actor->name,
            $this->section('KARTA', [
                'Imię i nazwisko' => $client->name,
                'Telefon' => $client->phone,
                'E-mail' => $client->email,
                'Opiekun' => $client->guardian,
                'Trener prowadzący' => $client->trainer?->name,
                'Stawka' => Money::format($client->rate).' za sesję',
                'Firma na rachunku' => $client->company_name,
                'NIP' => $client->tax_id,
                'Status' => $client->archived ? 'archiwalny' : 'aktywny',
                'W kartotece od' => $client->created_at?->format('d.m.Y'),
            ]),
            $this->section('ZGODY', [
                'Zgoda na przetwarzanie danych o zdrowiu' => $client->consent_given ? 'tak' : 'nie',
                'Data zgody' => $client->consent_date?->format('d.m.Y'),
            ]),
            $this->section('CEL I PUNKT STARTOWY', [
                'Cel' => $client->goal,
                'Punkt startowy' => $client->baseline,
            ]),
            $this->section('ZDROWIE', [
                'Kontuzje i przeciwwskazania' => $client->contraindications,
            ]),
            $this->section('NOTATKI TRENERA', [
                'Notatki' => $client->trainer_notes,
                'Na następny raz' => $client->next_session_plan,
            ]),
            $this->section('CHARAKTERYSTYKA', [
                'Tagi' => $client->tags->map(fn (ClientTag $tag) => $tag->label)->implode(', '),
            ]),
            $this->sessions($client),
            $this->prepayments($client),
            $this->files($client),
        ];

        $this->log->record($actor, 'Wyeksportował dane klienta', $client->name);

        return new DataFile(
            name: 'samtrening-dane-'.Str::slug($client->name).'-'.now()->format('Y-m-d').'.txt',
            contents: implode("\n\n", array_filter($parts, fn (string $part) => $part !== ''))."\n",
        );
    }

    /**
     * @param  array<string, string|null>  $rows
     */
    private function section(string $title, array $rows): string
    {
        $filled = array_filter($rows, fn (?string $value) => filled($value));

        if ($filled === []) {
            return '';
        }

        $body = collect($filled)->map(fn (string $value, string $label) => $label.': '.$value)->implode("\n");

        return $title."\n".str_repeat('-', mb_strlen($title))."\n".$body;
    }

    private function sessions(Client $client): string
    {
        if ($client->sessions->isEmpty()) {
            return '';
        }

        $rows = $client->sessions
            ->sortBy('date')
            ->map(fn (TrainingSession $session) => implode(' · ', array_filter([
                $session->date->format('d.m.Y'),
                $session->service,
                Money::format($session->price),
                $session->kind->label(),
                $session->payment_status->label(),
                $session->isPayable() && $session->prepaid_amount > 0
                    ? Money::format($session->prepaid_amount).' z przedpłaty'
                    : null,
                $session->notes,
            ])))
            ->implode("\n");

        return "HISTORIA SESJI\n".str_repeat('-', 14)."\n".$rows."\n\n"
            .'Nierozliczone saldo: '.Money::format($this->balance->forClient($client));
    }

    private function prepayments(Client $client): string
    {
        if ($client->prepayments->isEmpty()) {
            return '';
        }

        $rows = $client->prepayments
            ->sortBy('paid_on')
            ->map(fn (Prepayment $prepayment) => $prepayment->paid_on->format('d.m.Y').' · '.Money::format($prepayment->amount))
            ->implode("\n");

        return "WPŁATY Z GÓRY\n".str_repeat('-', 13)."\n".$rows."\n\n"
            .'Zostało z przedpłaty: '.Money::format($this->balance->prepayment($client)->left);
    }

    private function files(Client $client): string
    {
        if ($client->files->isEmpty()) {
            return '';
        }

        $rows = $client->files
            ->map(fn (ClientFile $file) => $file->name.' · '.$file->created_at?->format('d.m.Y'))
            ->implode("\n");

        return "PLIKI I PLANY\n".str_repeat('-', 13)."\n".$rows;
    }
}
