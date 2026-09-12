<?php

namespace App\Livewire\Trainer;

use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Queries\ClientOptions;
use App\Domain\Messaging\Actions\UpdateMessageTemplate;
use App\Domain\Messaging\Models\MessageTemplate;
use App\Domain\Messaging\SmsSegmentCounter;
use App\Domain\Messaging\TemplateRenderer;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * "Co dostaje klient." — docs/SPEC-EKRANY.md ekran 10. Every text is previewed with a real
 * client's data, because a template only looks right once it is full.
 */
class Messages extends Component
{
    /** @var array<string, array{name: string, when: string}> */
    private const array SMS_CARDS = [
        'payment_request' => [
            'name' => 'Prośba o płatność',
            'when' => 'Ręcznie, po wbiciu sesji na saldo.',
        ],
        'reminder' => [
            'name' => 'Monit o zaległej płatności',
            'when' => 'Automatycznie, po przekroczeniu progu z Ustawień.',
        ],
        'file_ready' => [
            'name' => 'Nowy plan do pobrania',
            'when' => 'Ręcznie, po wgraniu pliku na kartę klienta.',
        ],
        're_engagement' => [
            'name' => 'Zaczepka po ciszy',
            'when' => 'Ręcznie, z pulpitu — po 21 dniach bez sesji.',
        ],
    ];

    /** Template key => body, edited in place. */
    public array $bodies = [];

    public ?int $clientId = null;

    public function mount(ClientOptions $clients): void
    {
        $this->bodies = MessageTemplate::query()->pluck('body', 'key')->all();
        $this->clientId = array_key_first($clients->forTrainer(auth()->user()));
    }

    public function save(string $key): void
    {
        $this->validate(
            ['bodies.'.$key => ['required', 'string', 'max:2000']],
            ['bodies.'.$key.'.required' => 'Szablon nie może być pusty.'],
        );

        app(UpdateMessageTemplate::class)->handle(auth()->user(), $key, trim($this->bodies[$key]));

        $this->dispatch('toast', message: 'Szablon zapisany.');
    }

    public function render(ClientOptions $clients, TemplateRenderer $renderer, SmsSegmentCounter $counter): View
    {
        $client = $this->clientId ? Client::find($this->clientId) : null;

        if ($client && $client->trainer_id !== auth()->id() && ! auth()->user()->is_owner) {
            $client = null;
        }

        $context = $client
            ? $renderer->contextFor($client, extra: ['linkPliku' => 'samtrening.com/plik/9f3a…'])
            : [];

        $preview = fn (string $key) => $context === []
            ? ($this->bodies[$key] ?? '')
            : $renderer->render($this->bodies[$key] ?? '', $context);

        $sms = collect(self::SMS_CARDS)->map(function (array $card, string $key) use ($preview, $counter) {
            $text = $preview($key);

            return [...$card, 'key' => $key, 'preview' => $text, 'count' => $counter->count($text)];
        });

        return view('livewire.trainer.messages', [
            'clients' => $clients->forTrainer(auth()->user()),
            'hasClient' => $client !== null,
            'sms' => $sms,
            'subject' => $preview('statement_subject'),
            'body' => $preview('statement_body'),
        ]);
    }
}
