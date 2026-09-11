<?php

namespace App\Domain\Clients\Models;

use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client card. The balance is never stored here — `Billing\Balance` computes it from sessions.
 * `trainer_id` is set through `$trainer->clients()`, not by mass assignment.
 */
#[Fillable([
    'name', 'phone', 'email', 'rate', 'goal', 'baseline', 'contraindications', 'trainer_notes',
    'next_session_plan', 'guardian', 'consent_given', 'consent_date', 'company_name', 'tax_id', 'archived',
])]
#[UseFactory(ClientFactory::class)]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'contraindications' => 'encrypted',
            'consent_given' => 'boolean',
            'consent_date' => 'date',
            'archived' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * @return HasMany<ClientTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(ClientTag::class);
    }

    /**
     * @return HasMany<TrainingSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    /**
     * @return HasMany<ClientFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ClientFile::class);
    }
}
