<?php

namespace App\Domain\Training\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use Carbon\CarbonImmutable;
use Database\Factories\TrainingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A session logged after the fact. Logging it is the only moment money becomes due.
 */
#[Fillable(['date', 'service', 'price', 'kind', 'payment_status', 'notes'])]
#[UseFactory(TrainingSessionFactory::class)]
class TrainingSession extends Model
{
    /** @use HasFactory<TrainingSessionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'price' => 'integer',
            'kind' => SessionKind::class,
            'payment_status' => PaymentStatus::class,
            'notes' => 'encrypted',
        ];
    }

    /**
     * A session belongs to a day, not to a moment. Without this Eloquent writes
     * "2026-09-30 00:00:00": MySQL trims it to a DATE, SQLite keeps it, and the same range
     * query then drops its last day in the test suite only.
     *
     * @return Attribute<never, string>
     */
    protected function date(): Attribute
    {
        return Attribute::set(fn (mixed $value) => CarbonImmutable::parse($value)->toDateString());
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Still owed: neither paid nor waived — docs/START-TUTAJ.md §6.
     */
    public function isPayable(): bool
    {
        return $this->payment_status?->isPayable() ?? false;
    }

    /**
     * Held, as opposed to a cancellation or a no-show. A charged cancellation is money,
     * but it is not a session.
     */
    public function isCompleted(): bool
    {
        return $this->kind === SessionKind::Completed;
    }
}
