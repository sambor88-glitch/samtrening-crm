<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Jobs\SendSmsMessage;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\ClientCard;
use App\Livewire\Trainer\Payments;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(MessageTemplateSeeder::class);

    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska', 'blik_number' => '600 100 200']);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'phone' => '+48 600 300 400',
        'rate' => 20000,
    ]);
    TrainingSession::factory()->for($this->client)->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->create(['price' => 20000]);
});

test('asking for BLIK queues the text with the trainer\'s own number and marks the sessions asked', function () {
    Queue::fake();

    Livewire::actingAs($this->trainer)->test(Payments::class)
        ->call('requestBlik', $this->client->id)
        ->assertDispatched('toast', message: 'Prośba o BLIK do Magdalena Wróbel — 400 zł. SMS poszedł do kolejki.');

    Queue::assertPushed(SendSmsMessage::class, function (SendSmsMessage $job) {
        return $job->phone === '+48 600 300 400'
            && str_contains($job->text, 'BLIK na 600 100 200')
            && str_contains($job->text, 'Cześć Magdalena!');
    });

    expect($this->client->sessions()->where('payment_status', PaymentStatus::Requested)->count())->toBe(2)
        ->and(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Poprosił o BLIK')
        ->context->toBe('Magdalena Wróbel · 400 zł');
});

test('the same button on the client card does the same thing', function () {
    Queue::fake();

    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->client])
        ->call('requestBlik')
        ->assertDispatched('toast', message: 'Prośba o BLIK do Magdalena Wróbel — 400 zł. SMS poszedł do kolejki.');

    Queue::assertPushed(SendSmsMessage::class);

    expect($this->client->sessions()->where('payment_status', PaymentStatus::Requested)->count())->toBe(2);
});

test('a trainer without a BLIK number is told to fix it, and nothing changes', function () {
    Queue::fake();

    $this->trainer->update(['blik_number' => null]);

    Livewire::actingAs($this->trainer->fresh())->test(Payments::class)
        ->call('requestBlik', $this->client->id)
        ->assertDispatched('toast', function (string $event, array $params) {
            return $params['variant'] === 'error'
                && str_contains($params['message'], 'Nie masz numeru BLIK w Ustawieniach');
        });

    Queue::assertNothingPushed();

    expect($this->client->sessions()->where('payment_status', PaymentStatus::Balance)->count())->toBe(2)
        ->and(ActivityEntry::query()->where('action', 'Poprosił o BLIK')->count())->toBe(0);
});

test('a client with no phone number blocks the request too', function () {
    Queue::fake();

    $this->client->update(['phone' => null]);

    Livewire::actingAs($this->trainer)->test(Payments::class)
        ->call('requestBlik', $this->client->id)
        ->assertDispatched('toast', function (string $event, array $params) {
            return $params['variant'] === 'error' && str_contains($params['message'], 'nie ma numeru telefonu');
        });

    Queue::assertNothingPushed();
});

test('paid sessions are not dragged back into a request', function () {
    Queue::fake();

    $paid = TrainingSession::factory()->for($this->client)->paid()->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(Payments::class)->call('requestBlik', $this->client->id);

    expect($paid->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});
