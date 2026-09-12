<?php

namespace App\Domain\Messaging\Jobs;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Providers\SmsProvider;
use App\Domain\Team\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sending is a separate operation from whatever triggered it — docs/START-TUTAJ.md §9. A session
 * stays logged even if the carrier is down; all that happens is a line in the log saying the
 * message never left.
 */
class SendSmsMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly int $clientId,
        public readonly string $phone,
        public readonly string $text,
        public readonly ?int $actorId,
        public readonly string $subject,
    ) {}

    public function handle(SmsProvider $provider): void
    {
        $provider->send($this->phone, $this->text);
    }

    /**
     * Out of retries. The trainer has to know, and the log is where they will look.
     */
    public function failed(?Throwable $exception): void
    {
        app(ActivityLogger::class)->record(
            $this->actorId ? User::find($this->actorId) : null,
            'Wiadomość nie wyszła',
            trim((Client::find($this->clientId)?->name ?? 'Klient').' · '.$this->subject),
        );
    }
}
