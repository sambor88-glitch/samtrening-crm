<?php

namespace App\Domain\Messaging\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Mail\MonthlyStatement;
use App\Domain\Messaging\MessageNotPossible;
use App\Domain\Messaging\Models\MessageTemplate;
use App\Domain\Messaging\TemplateRenderer;
use App\Domain\Team\Models\User;
use App\Support\DateRange;
use Illuminate\Support\Facades\Mail;

/**
 * One e-mail per client: the sessions from the chosen month, what they add up to and the
 * trainer's BLIK number. No running balance — showing the whole debt next to a month's total
 * confused people, which is why {saldo} was taken out of this template (docs §9).
 */
class SendMonthlyStatement
{
    public function __construct(private readonly TemplateRenderer $renderer) {}

    /**
     * @throws MessageNotPossible
     */
    public function handle(User $trainer, Client $client, DateRange $month): void
    {
        if (blank($client->email)) {
            throw MessageNotPossible::withoutEmail($client);
        }

        $context = $this->renderer->contextFor($client, $month);

        Mail::to($client->email)->queue(new MonthlyStatement(
            subjectLine: $this->renderer->render($this->template('statement_subject'), $context),
            bodyText: $this->renderer->render($this->template('statement_body'), $context),
            trainerEmail: $trainer->email,
            trainerName: $trainer->name,
        ));
    }

    private function template(string $key): string
    {
        return (string) MessageTemplate::query()->where('key', $key)->value('body');
    }
}
