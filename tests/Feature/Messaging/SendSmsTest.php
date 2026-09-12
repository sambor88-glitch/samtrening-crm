<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Actions\SendSms;
use App\Domain\Messaging\Jobs\SendSmsMessage;
use App\Domain\Messaging\Providers\LogSmsProvider;
use App\Domain\Messaging\Providers\SmsProvider;
use App\Domain\Messaging\SmsNotPossible;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska', 'blik_number' => '600 100 200']);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'phone' => '+48 600 300 400',
    ]);
});

test('until a carrier is chosen, messages go to the log and nowhere else', function () {
    expect(app(SmsProvider::class))->toBeInstanceOf(LogSmsProvider::class);
});

test('a message is queued, never sent while the trainer waits', function () {
    Queue::fake();

    $count = app(SendSms::class)->handle(
        sender: $this->trainer,
        client: $this->client,
        text: 'Cześć Magdalena!',
        subject: 'prośba o BLIK',
        actor: $this->trainer,
    );

    Queue::assertPushed(SendSmsMessage::class, function (SendSmsMessage $job) {
        return $job->phone === '+48 600 300 400'
            && $job->text === 'Cześć Magdalena!'
            && $job->actorId === $this->trainer->id
            && $job->subject === 'prośba o BLIK';
    });

    expect($count->segments)->toBe(1)
        ->and($count->encoding)->toBe('UCS-2');
});

test('the job hands the text to whichever carrier is bound', function () {
    $carrier = new class implements SmsProvider
    {
        public array $sent = [];

        public function send(string $phone, string $text): void
        {
            $this->sent[] = [$phone, $text];
        }
    };

    (new SendSmsMessage($this->client->id, '+48 600 300 400', 'Treść', $this->trainer->id, 'monit'))
        ->handle($carrier);

    expect($carrier->sent)->toBe([['+48 600 300 400', 'Treść']]);
});

test('a carrier that fails for good leaves the session alone and says so in the log', function () {
    $session = TrainingSession::factory()->for($this->client)->create();

    $job = new SendSmsMessage($this->client->id, '+48 600 300 400', 'Treść', $this->trainer->id, 'prośba o BLIK');

    $job->failed(new RuntimeException('Brama SMS nie odpowiada'));

    expect(TrainingSession::query()->whereKey($session->id)->exists())->toBeTrue()
        ->and(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Wiadomość nie wyszła')
        ->context->toBe('Magdalena Wróbel · prośba o BLIK')
        ->actor_name->toBe('Katarzyna Samborska');
});

test('a trainer without a BLIK number sends nothing and is told where to fix it', function () {
    Queue::fake();

    $this->trainer->update(['blik_number' => null]);

    expect(fn () => app(SendSms::class)->handle($this->trainer->fresh(), $this->client, 'Treść', 'monit'))
        ->toThrow(SmsNotPossible::class, 'Nie masz numeru BLIK w Ustawieniach — bez niego SMS poszedłby z pustym numerem.');

    Queue::assertNothingPushed();
});

test('a client without a phone number sends nothing either', function () {
    Queue::fake();

    $this->client->update(['phone' => null]);

    expect(fn () => app(SendSms::class)->handle($this->trainer, $this->client->fresh(), 'Treść', 'monit'))
        ->toThrow(SmsNotPossible::class, 'Magdalena Wróbel nie ma numeru telefonu na karcie. Uzupełnij go w edycji karty.');

    Queue::assertNothingPushed();
});

test('the job retries a few times before giving up', function () {
    $job = new SendSmsMessage($this->client->id, '+48 600 300 400', 'Treść', $this->trainer->id, 'monit');

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([60, 300]);
});
