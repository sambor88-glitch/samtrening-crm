<?php

namespace App\Domain\Billing\Export;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use App\Support\DateRange;
use App\Support\Plural;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The file the accountant opens — docs/START-TUTAJ.md §10. Semicolons and a UTF-8 BOM, because
 * without them Polish Excel mangles "ą" and puts the whole row in one cell. Session notes never
 * leave this app: they are health data, and bookkeeping has no use for them.
 */
class SessionCsvExport
{
    public function __construct(private readonly ActivityLogger $log) {}

    /**
     * One trainer's own sessions.
     */
    public function forTrainer(User $trainer, DateRange $range): CsvFile
    {
        $sessions = $this->sessions($range)
            ->whereHas('client', fn (Builder $client) => $client->forTrainer($trainer))
            ->with('client:id,name')
            ->get();

        $rows = $sessions->map(fn (TrainingSession $session) => [
            $session->date->format('Y-m-d'),
            $session->client->name,
            $session->service,
            $this->kind($session->kind),
            $this->amount($session->price),
            $this->status($session->payment_status),
        ]);

        return $this->file(
            actor: $trainer,
            name: 'samtrening-'.$range->prefix().'-'.Str::slug(Str::before($trainer->name, ' ')).'.csv',
            headers: ['Data', 'Klient', 'Usługa', 'Rodzaj', 'Kwota PLN', 'Status płatności'],
            rows: $rows,
            range: $range,
        );
    }

    /**
     * The whole studio, with the trainer named — the owner's version (button lands in SC-35).
     */
    public function forStudio(User $actor, DateRange $range): CsvFile
    {
        $sessions = $this->sessions($range)->with('client.trainer:id,name')->get();

        $rows = $sessions->map(fn (TrainingSession $session) => [
            $session->date->format('Y-m-d'),
            $session->client->name,
            $session->client->trainer->name,
            $session->service,
            $this->kind($session->kind),
            $this->amount($session->price),
            $this->status($session->payment_status),
        ]);

        return $this->file(
            actor: $actor,
            name: 'samtrening-studio-'.$range->prefix().'.csv',
            headers: ['Data', 'Klient', 'Trener', 'Usługa', 'Rodzaj', 'Kwota PLN', 'Status płatności'],
            rows: $rows,
            range: $range,
        );
    }

    /**
     * @return Builder<TrainingSession>
     */
    private function sessions(DateRange $range): Builder
    {
        return TrainingSession::query()
            ->whereBetween('date', [$range->firstDay(), $range->lastDay()])
            ->orderBy('date')
            ->orderBy('id');
    }

    /**
     * @param  array<int, string>  $headers
     * @param  Collection<int, array<int, string>>  $rows
     */
    private function file(User $actor, string $name, array $headers, Collection $rows, DateRange $range): CsvFile
    {
        $handle = fopen('php://temp', 'r+');

        // The BOM is what tells Excel the file is UTF-8; \r\n is what keeps Windows happy.
        fwrite($handle, "\u{FEFF}");
        fputcsv($handle, $headers, ';', '"', '', "\r\n");

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '', "\r\n");
        }

        rewind($handle);
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        $this->log->record(
            $actor,
            'Wyeksportował CSV',
            $range->label().' · '.Plural::of($rows->count(), 'wiersz', 'wiersze', 'wierszy'),
        );

        return new CsvFile($name, $contents, $rows->count());
    }

    /**
     * Złoty without the currency: "200" and "199,50", the way Polish Excel reads a number.
     */
    private function amount(int $grosze): string
    {
        return $grosze % 100 === 0
            ? (string) intdiv($grosze, 100)
            : number_format($grosze / 100, 2, ',', '');
    }

    private function kind(SessionKind $kind): string
    {
        return match ($kind) {
            SessionKind::Completed => 'Odbyta',
            SessionKind::Cancelled => 'Odwołana',
            SessionKind::NoShow => 'Nieobecność',
        };
    }

    private function status(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Paid => 'Zapłacone',
            PaymentStatus::Balance => 'Na saldzie',
            PaymentStatus::Requested => 'Poproszono',
            PaymentStatus::Waived => 'Nie naliczono',
        };
    }
}
