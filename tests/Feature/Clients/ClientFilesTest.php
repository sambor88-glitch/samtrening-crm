<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Models\ClientFile;
use App\Domain\Messaging\Jobs\SendSmsMessage;
use App\Domain\Team\Models\User;
use App\Livewire\Trainer\ClientCard;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(MessageTemplateSeeder::class);

    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska', 'blik_number' => '600 100 200']);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'phone' => '+48 600 300 400',
    ]);
});

test('an uploaded plan lands on the private disk and on the card', function () {
    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->client])
        ->set('upload', UploadedFile::fake()->create('plan-wrzesien.pdf', 400, 'application/pdf'))
        ->call('uploadFile')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Plik plan-wrzesien.pdf jest na karcie.')
        ->assertSee('plan-wrzesien.pdf');

    $file = ClientFile::query()->sole();

    expect($file->client_id)->toBe($this->client->id)
        ->and($file->extension)->toBe('PDF')
        ->and($file->path)->toStartWith('klienci/'.$this->client->id.'/');

    Storage::disk('local')->assertExists($file->path);

    expect(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Wgrał plik')
        ->context->toBe('Magdalena Wróbel · plan-wrzesien.pdf');
});

test('an executable or an oversized file is refused', function () {
    $card = Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->client]);

    $card->set('upload', UploadedFile::fake()->create('wirus.exe', 10))
        ->call('uploadFile')
        ->assertHasErrors(['upload' => 'mimes']);

    $card->set('upload', UploadedFile::fake()->create('ogromny.pdf', 9000, 'application/pdf'))
        ->call('uploadFile')
        ->assertHasErrors(['upload' => 'max']);

    expect(ClientFile::query()->count())->toBe(0);
});

test('sending a file texts a link that works, and stops working after fourteen days', function () {
    Queue::fake();

    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->client])
        ->set('upload', UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf'))
        ->call('uploadFile');

    $file = ClientFile::query()->sole();

    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->client])
        ->call('sendFile', $file->id)
        ->assertDispatched('toast', message: 'Link do „plan.pdf" poszedł SMS-em. Wygasa za 14 dni.');

    $link = null;
    Queue::assertPushed(SendSmsMessage::class, function (SendSmsMessage $job) use (&$link) {
        preg_match('#https?://\S+#', $job->text, $matches);
        $link = $matches[0] ?? null;

        return str_contains($job->text, 'Twój plan jest gotowy');
    });

    // A client opens it with no account at all — and Livewire::actingAs would otherwise leave
    // the trainer logged in for the rest of the test, which is not the case being checked.
    auth()->logout();

    $this->get($link)->assertOk()->assertDownload('plan.pdf');

    $this->travel(15)->days();
    $this->get($link)->assertForbidden();

    expect(ActivityEntry::query()->where('action', 'Wysłał plik')->first()->context)
        ->toBe('Magdalena Wróbel · plan.pdf');
});

test('the file sits under no public address', function () {
    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->client])
        ->set('upload', UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf'))
        ->call('uploadFile');

    $file = ClientFile::query()->sole();

    expect($file->path)->not->toContain('public');

    Storage::disk('public')->assertMissing($file->path);

    // Without a signature and without a session, the link is simply refused.
    auth()->logout();

    $this->get(route('client-files.show', $file))->assertForbidden();
});

test('a trainer cannot pull a file from another trainer\'s client, even knowing the id', function () {
    $theirs = Client::factory()->for(User::factory()->create(), 'trainer')->create();
    $file = $theirs->files()->create([
        'name' => 'cudzy-plan.pdf',
        'extension' => 'PDF',
        'path' => 'klienci/'.$theirs->id.'/cudzy.pdf',
        'size' => 1000,
    ]);

    $this->actingAs($this->trainer)
        ->get(route('client-files.show', $file))
        ->assertForbidden();
});

test('the owner reaches any client\'s file, as everywhere else', function () {
    Storage::disk('local')->put('klienci/x/plan.pdf', 'treść');

    $file = $this->client->files()->create([
        'name' => 'plan.pdf',
        'extension' => 'PDF',
        'path' => 'klienci/x/plan.pdf',
        'size' => 6,
    ]);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('client-files.show', $file))
        ->assertOk();
});

test('a link signed for somebody else\'s file is not a link to this one', function () {
    Storage::disk('local')->put('klienci/x/plan.pdf', 'treść');

    $mine = $this->client->files()->create(['name' => 'a.pdf', 'extension' => 'PDF', 'path' => 'klienci/x/plan.pdf', 'size' => 6]);
    $other = $this->client->files()->create(['name' => 'b.pdf', 'extension' => 'PDF', 'path' => 'klienci/x/plan.pdf', 'size' => 6]);

    $link = URL::temporarySignedRoute('client-files.show', now()->addDays(14), ['file' => $mine->getKey()]);
    $tampered = str_replace('/p/'.$mine->getKey(), '/p/'.$other->getKey(), $link);

    $this->get($tampered)->assertForbidden();
});
