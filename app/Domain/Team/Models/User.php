<?php

namespace App\Domain\Team\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Notifications\ResetPasswordNotification;
use App\Policies\UserPolicy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A trainer account. `status` and `is_owner` are left out of mass assignment on purpose —
 * only the owner-side actions and the seeder may change them.
 */
#[Fillable(['name', 'email', 'password', 'specialty', 'blik_number'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_owner' => 'boolean',
        ];
    }

    /**
     * Clients whose card this trainer keeps.
     *
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'trainer_id');
    }

    /**
     * The reset mail is ours and speaks Polish; the framework only hands over the token.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
