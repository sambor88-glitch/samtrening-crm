<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Jobs\SendSmsMessage;
use App\Domain\Settings\Models\Setting;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(MessageTemplateSeeder::class);

    Setting::query()->create(['reminders_enabled' => true, 'reminder_threshold_days' => 14]);

    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska', 'blik_number' => '600 100 200']);
    $this->overdue = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'phone' => '+48 600 300 400',
    ]);
    TrainingSession::factory()->for($this->overdue)->on(now()->subDays(20)->toDateString())->create(['price' => 20000]);
});

test('the daily run nudges a debt past the threshold, once', function () {
    Queue::fake();

    $this->artisan('samtrening:monity')->assertSuccessful();

    Queue::assertPushed(SendSmsMessage::class, function (SendSmsMessage $job) {
        return $job->phone === '+48 600 300 400'
            && str_contains($job->text, 'BLIK na 600 100 200')
            && str_contains($job->text, '200 zł')
            && $job->actorId === null;
    });

    expect($this->overdue->fresh()->last_reminder_at)->not->toBeNull()
        ->and(ActivityEntry::query()->where('action', 'Wysłał monit')->first()->actor_name)->toBe('System');
});

test('a client nudged this week is left alone, and hears again after a week', function () {
    Queue::fake();

    $this->artisan('samtrening:monity');
    $this->artisan('samtrening:monity');

    Queue::assertPushed(SendSmsMessage::class, 1);

    $this->travel(8)->days();
    $this->artisan('samtrening:monity');

    Queue::assertPushed(SendSmsMessage::class, 2);
});

test('a debt younger than the threshold waits', function () {
    Queue::fake();

    $fresh = Client::factory()->for($this->trainer, 'trainer')->create(['phone' => '+48 600 500 600']);
    TrainingSession::factory()->for($fresh)->on(now()->subDays(10)->toDateString())->create(['price' => 20000]);

    $this->artisan('samtrening:monity');

    Queue::assertPushed(SendSmsMessage::class, 1);
});

test('with reminders switched off the run sends nothing at all', function () {
    Queue::fake();

    Setting::query()->update(['reminders_enabled' => false]);

    $this->artisan('samtrening:monity')->expectsOutputToContain('Monity są wyłączone');

    Queue::assertNothingPushed();
});

test('a trainer without a BLIK number is skipped with a reason, the rest still go out', function () {
    Queue::fake();

    $otherTrainer = User::factory()->create(['name' => 'Bartek Nowak', 'blik_number' => null]);
    $theirClient = Client::factory()->for($otherTrainer, 'trainer')->create([
        'name' => 'Aleksander Górski',
        'phone' => '+48 600 700 800',
    ]);
    TrainingSession::factory()->for($theirClient)->on(now()->subDays(30)->toDateString())->create(['price' => 20000]);

    $this->artisan('samtrening:monity');

    Queue::assertPushed(SendSmsMessage::class, 1);

    expect(ActivityEntry::query()->where('action', 'Monit pominięty')->first())
        ->context->toContain('Aleksander Górski')
        ->context->toContain('Nie masz numeru BLIK');
});

test('every trainer\'s own number goes into their own clients\' reminders', function () {
    Queue::fake();

    $bartek = User::factory()->create(['name' => 'Bartek Nowak', 'blik_number' => '999 888 777']);
    $his = Client::factory()->for($bartek, 'trainer')->create(['name' => 'Aleksander Górski', 'phone' => '+48 600 700 800']);
    TrainingSession::factory()->for($his)->on(now()->subDays(30)->toDateString())->create(['price' => 22000]);

    $this->artisan('samtrening:monity');

    Queue::assertPushed(SendSmsMessage::class, fn (SendSmsMessage $job) => $job->phone === '+48 600 300 400'
        && str_contains($job->text, '600 100 200'));

    Queue::assertPushed(SendSmsMessage::class, fn (SendSmsMessage $job) => $job->phone === '+48 600 700 800'
        && str_contains($job->text, '999 888 777'));
});
