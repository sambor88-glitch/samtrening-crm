<?php

namespace App\Domain\Messaging\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The month's statement — docs/START-TUTAJ.md §9. Sent from the studio's address but answered by
 * the trainer: the client writes back to the person they train with, not to a mailbox nobody
 * reads. Plain text on purpose; the text is the whole message.
 */
class MonthlyStatement extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyText,
        public readonly string $trainerEmail,
        public readonly string $trainerName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: [new Address($this->trainerEmail, $this->trainerName)],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.statement', with: ['body' => $this->bodyText]);
    }
}
