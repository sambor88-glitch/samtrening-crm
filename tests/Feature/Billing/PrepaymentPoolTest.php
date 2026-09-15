<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Actions\DeletePrepayment;
use App\Domain\Billing\Actions\MarkAsPaid;
use App\Domain\Billing\Actions\RecordPrepayment;
use App\Domain\Billing\Actions\RestorePrepayment;
use App\Domain\Billing\Balance;
use App\Domain\Billing\Models\Prepayment;
use App\Domain\Billing\PrepaymentPool;
use App\Domain\Billing\Queries\Outstanding;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Actions\DeleteSession;
use App\Domain\Training\Actions\LogSession;
use App\Domain\Training\Actions\RestoreSession;
use App\Domain\Training\Actions\UpdateSessionPrice;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use Carbon\CarbonImmutable;

/**
 * docs/START-TUTAJ.md §6, "Przedpłata": the pool pays for the sessions still owed, oldest first,
 * and every change to a prepayment, a price or a session works the client out again. Everything
 * runs through the real actions, because that is where the pool is called from.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));

    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska']);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'rate' => 20000,
    ]);
    $this->balance = app(Balance::class);
});

function prepayUpFront(int $amount, string $on = '2026-09-01'): Prepayment
{
    return app(RecordPrepayment::class)->handle(test()->trainer, test()->client, $amount, $on);
}

function logSessionOn(string $on, int $price = 20000): TrainingSession
{
    return app(LogSession::class)->handle(test()->trainer, test()->client, [
        'date' => $on,
        'service' => 'Trening personalny 1:1',
        'price' => $price,
        'kind' => SessionKind::Completed,
        'payment_status' => PaymentStatus::Balance,
    ]);
}

/**
 * The card in one list, oldest first: "day status złoty-from-the-pool".
 *
 * @return list<string>
 */
function poolState(Client $client): array
{
    return $client->sessions()->orderBy('date')->orderBy('id')->get()
        ->map(fn (TrainingSession $session) => $session->date->format('d.m').' '
            .$session->payment_status->value.' '.intdiv($session->prepaid_amount, 100))
        ->all();
}

test('a prepayment pays for the sessions still owed, oldest first, until it runs out', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-05')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-01')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-03')->create();

    prepayUpFront(50000);

    expect(poolState($this->client))->toBe([
        '01.09 prepaid 200',
        '03.09 prepaid 200',
        '05.09 balance 100',
    ])
        ->and($this->balance->forClient($this->client))->toBe(10000)
        ->and($this->balance->prepayment($this->client))
        ->paidIn->toBe(50000)
        ->used->toBe(50000)
        ->left->toBe(0);
});

test('sessions logged after the prepayment draw from it, and the first it cannot pay is beyond it', function () {
    prepayUpFront(40000);

    $first = logSessionOn('2026-09-02');
    logSessionOn('2026-09-04');
    $beyond = logSessionOn('2026-09-06');

    expect(poolState($this->client))->toBe([
        '02.09 prepaid 200',
        '04.09 prepaid 200',
        '06.09 balance 0',
    ])
        ->and($first->payment_status)->toBe(PaymentStatus::Prepaid)
        ->and($beyond->payment_status)->toBe(PaymentStatus::Balance)
        ->and($this->balance->forClient($this->client))->toBe(20000);

    expect(ActivityEntry::query()->where('action', 'Wbił sesję')->orderBy('id')->pluck('context')->all())->toBe([
        'Magdalena Wróbel · 200 zł · z przedpłaty',
        'Magdalena Wróbel · 200 zł · z przedpłaty',
        'Magdalena Wróbel · 200 zł · na saldo',
    ]);
});

test('the session the pool runs out on is owed only the rest', function () {
    prepayUpFront(30000);

    logSessionOn('2026-09-02');
    $partial = logSessionOn('2026-09-04');

    expect($partial)
        ->payment_status->toBe(PaymentStatus::Balance)
        ->prepaid_amount->toBe(10000)
        ->and($partial->beyondPrepayment())->toBe(10000)
        ->and($this->balance->forClient($this->client))->toBe(10000)
        ->and(ActivityEntry::query()->orderByDesc('id')->first()->context)
        ->toBe('Magdalena Wróbel · 200 zł · na saldo · 100 zł z przedpłaty');
});

test('money left in the pool waits for the sessions to come', function () {
    prepayUpFront(100000);

    logSessionOn('2026-09-02');
    logSessionOn('2026-09-04');

    expect($this->balance->prepayment($this->client))
        ->used->toBe(40000)
        ->left->toBe(60000)
        ->and($this->balance->prepayment($this->client)->sessionsLeft(20000))->toBe(3)
        ->and($this->balance->forClient($this->client))->toBe(0);
});

test('a prepayment pays off an older debt before it waits for new sessions', function () {
    TrainingSession::factory()->for($this->client)->on('2026-08-20')->create();
    TrainingSession::factory()->for($this->client)->on('2026-08-27')->requested()->create();

    prepayUpFront(100000, '2026-09-10');

    expect(poolState($this->client))->toBe([
        '20.08 prepaid 200',
        '27.08 prepaid 200',
    ])
        ->and($this->balance->prepayment($this->client)->left)->toBe(60000)
        ->and(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Zapisał wpłatę z góry')
        ->context->toBe('Magdalena Wróbel · 1 000 zł · 10.09.2026 · pokryła 400 zł salda');
});

test('sessions paid on the spot and free cancellations leave the pool alone', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-01')->paid()->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->waived()->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-03')->create();

    prepayUpFront(40000);

    expect(poolState($this->client))->toBe([
        '01.09 paid 0',
        '02.09 waived 0',
        '03.09 prepaid 200',
    ])
        ->and($this->balance->prepayment($this->client)->left)->toBe(20000);
});

test('a charged cancellation and a no-show are paid out of the pool like a session', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-01')->cancelled()->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->noShow()->create();

    prepayUpFront(40000);

    expect(poolState($this->client))->toBe([
        '01.09 prepaid 200',
        '02.09 prepaid 200',
    ])
        ->and($this->balance->forClient($this->client))->toBe(0);
});

test('a session logged late for an earlier day is paid first, and the latest one steps out of the pool', function () {
    prepayUpFront(40000);

    logSessionOn('2026-09-05');
    logSessionOn('2026-09-07');
    logSessionOn('2026-09-01');

    expect(poolState($this->client))->toBe([
        '01.09 prepaid 200',
        '05.09 prepaid 200',
        '07.09 balance 0',
    ]);
});

test('a cheaper session leaves money for the next one, a dearer one pushes the next out', function () {
    prepayUpFront(40000);

    $first = logSessionOn('2026-09-01');
    logSessionOn('2026-09-03');
    logSessionOn('2026-09-05');

    app(UpdateSessionPrice::class)->handle($this->trainer, $first, 15000);

    expect(poolState($this->client))->toBe([
        '01.09 prepaid 150',
        '03.09 prepaid 200',
        '05.09 balance 50',
    ])
        ->and($this->balance->forClient($this->client))->toBe(15000);

    app(UpdateSessionPrice::class)->handle($this->trainer, $first->fresh(), 30000);

    expect(poolState($this->client))->toBe([
        '01.09 prepaid 300',
        '03.09 balance 100',
        '05.09 balance 0',
    ])
        ->and($this->balance->forClient($this->client))->toBe(30000);
});

test('deleting a session paid from the pool gives its money back, and Cofnij takes it again', function () {
    prepayUpFront(40000);

    $first = logSessionOn('2026-09-01');
    logSessionOn('2026-09-03');
    logSessionOn('2026-09-05');

    app(DeleteSession::class)->handle($this->trainer, $first);

    expect(poolState($this->client))->toBe([
        '03.09 prepaid 200',
        '05.09 prepaid 200',
    ])
        ->and(ActivityEntry::query()->orderByDesc('id')->first()->context)
        ->toBe('Magdalena Wróbel · 01.09.2026 · 200 zł · wraca do przedpłaty');

    app(RestoreSession::class)->handle($this->trainer, $first);

    expect(poolState($this->client))->toBe([
        '01.09 prepaid 200',
        '03.09 prepaid 200',
        '05.09 balance 0',
    ]);
});

test('taking a prepayment back puts its sessions back on the balance, and Cofnij pays them again', function () {
    $prepayment = prepayUpFront(40000);

    logSessionOn('2026-09-01');
    logSessionOn('2026-09-03');

    app(DeletePrepayment::class)->handle($this->trainer, $prepayment);

    expect(poolState($this->client))->toBe([
        '01.09 balance 0',
        '03.09 balance 0',
    ])
        ->and($this->balance->forClient($this->client))->toBe(40000)
        ->and($this->balance->prepayment($this->client)->isEmpty())->toBeTrue()
        ->and(Prepayment::withTrashed()->count())->toBe(1)
        ->and(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Usunął wpłatę z góry')
        ->context->toBe('Magdalena Wróbel · 01.09.2026 · 400 zł');

    app(RestorePrepayment::class)->handle($this->trainer, $prepayment);

    expect(poolState($this->client))->toBe([
        '01.09 prepaid 200',
        '03.09 prepaid 200',
    ])
        ->and(ActivityEntry::query()->orderByDesc('id')->first()->action)->toBe('Cofnął usunięcie wpłaty z góry');
});

test('a second Cofnij finds nothing to restore and writes nothing', function () {
    $prepayment = prepayUpFront(40000);

    app(DeletePrepayment::class)->handle($this->trainer, $prepayment);
    app(RestorePrepayment::class)->handle($this->trainer, $prepayment);
    app(RestorePrepayment::class)->handle($this->trainer, $prepayment);

    expect(ActivityEntry::query()->where('action', 'Cofnął usunięcie wpłaty z góry')->count())->toBe(1);
});

test('paying the rest in cash keeps the pool\'s share spent, so a top-up is not counted twice', function () {
    prepayUpFront(30000);

    logSessionOn('2026-09-01');
    logSessionOn('2026-09-03');

    expect(app(MarkAsPaid::class)->handle($this->trainer, $this->client))->toBe(10000);

    expect(poolState($this->client))->toBe([
        '01.09 prepaid 200',
        '03.09 paid 100',
    ])
        ->and($this->balance->forClient($this->client))->toBe(0)
        ->and($this->balance->prepayment($this->client))->used->toBe(30000)->left->toBe(0)
        ->and(ActivityEntry::query()->orderByDesc('id')->first()->context)->toBe('Magdalena Wróbel · 100 zł');

    prepayUpFront(20000, '2026-09-10');
    logSessionOn('2026-09-12');

    expect(poolState($this->client))->toBe([
        '01.09 prepaid 200',
        '03.09 paid 100',
        '12.09 prepaid 200',
    ])
        ->and($this->balance->prepayment($this->client)->left)->toBe(0);
});

test('a payment request stays a request while any of it is still owed', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-01')->requested()->create();

    prepayUpFront(10000);

    expect(poolState($this->client))->toBe(['01.09 requested 100'])
        ->and($this->balance->forClient($this->client))->toBe(10000);

    prepayUpFront(10000, '2026-09-02');

    expect(poolState($this->client))->toBe(['01.09 prepaid 200']);
});

test('the arrears and every balance read only what the prepayment left owed', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-01')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-03')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-05')->create();

    prepayUpFront(30000);

    $row = app(Outstanding::class)->forTrainer($this->trainer)->sole();

    expect($row)
        ->sessions->toBe(2)
        ->amount->toBe(30000)
        ->and($row->oldestOn->toDateString())->toBe('2026-09-03')
        ->and($this->balance->forClients([$this->client->id]))->toBe([$this->client->id => 30000])
        ->and($this->balance->owedSince($this->client)->toDateString())->toBe('2026-09-03')
        ->and(app(Outstanding::class)->totalForTrainer($this->trainer))->toBe(30000)
        ->and(app(Outstanding::class)->totalForStudio())->toBe(30000);
});

test('a client who never paid up front is left exactly as they were', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-01')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->requested()->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-03')->paid()->create();

    app(PrepaymentPool::class)->allocate($this->client);

    expect(poolState($this->client))->toBe([
        '01.09 balance 0',
        '02.09 requested 0',
        '03.09 paid 0',
    ]);
});

test('a price cut never leaves more paid from the pool than the session costs', function () {
    prepayUpFront(30000);

    logSessionOn('2026-09-01');
    $partial = logSessionOn('2026-09-03');

    app(MarkAsPaid::class)->handle($this->trainer, $this->client);
    app(UpdateSessionPrice::class)->handle($this->trainer, $partial->fresh(), 5000);

    expect(poolState($this->client))->toBe([
        '01.09 prepaid 200',
        '03.09 paid 50',
    ])
        ->and($this->balance->prepayment($this->client))->used->toBe(25000)->left->toBe(5000);
});

test('the pool never spends more than was paid in', function () {
    $prepayment = prepayUpFront(30000);

    logSessionOn('2026-09-01');
    logSessionOn('2026-09-03');
    app(MarkAsPaid::class)->handle($this->trainer, $this->client);

    app(DeletePrepayment::class)->handle($this->trainer, $prepayment);

    expect(poolState($this->client))->toBe([
        '01.09 balance 0',
        '03.09 paid 0',
    ])
        ->and($this->balance->prepayment($this->client))->used->toBe(0)->left->toBe(0)
        ->and($this->balance->forClient($this->client))->toBe(20000);
});
