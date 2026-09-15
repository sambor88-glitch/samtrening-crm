<?php

namespace App\Domain\Billing\Models;

use App\Domain\Clients\Models\Client;
use Carbon\CarbonImmutable;
use Database\Factories\PrepaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money a client paid up front, in grosze. It pays for sessions only through
 * `Billing\PrepaymentPool` — nothing else decides which sessions that is. Access follows the
 * client card, the way files do.
 */
#[Fillable(['amount', 'paid_on'])]
#[UseFactory(PrepaymentFactory::class)]
class Prepayment extends Model
{
    /** @use HasFactory<PrepaymentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_on' => 'date',
        ];
    }

    /**
     * A day, not a moment — the same trap as TrainingSession::date().
     *
     * @return Attribute<never, string>
     */
    protected function paidOn(): Attribute
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
}
