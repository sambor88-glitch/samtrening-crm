<?php

namespace Database\Factories;

use App\Domain\Clients\Models\Client;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingSession>
 */
class TrainingSessionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TrainingSession>
     */
    protected $model = TrainingSession::class;

    /**
     * Define the model's default state: a completed session, charged at the client's rate, still unpaid.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'date' => fake()->dateTimeBetween('-30 days')->format('Y-m-d'),
            'service' => 'Trening personalny 1:1',
            'price' => fn (array $attributes) => Client::query()->find($attributes['client_id'])?->rate ?? 20000,
            'kind' => SessionKind::Completed,
            'payment_status' => PaymentStatus::Balance,
        ];
    }

    /**
     * A session the client has already paid for.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Paid,
        ]);
    }

    /**
     * A payment request went out; the money is still owed.
     */
    public function requested(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Requested,
        ]);
    }

    /**
     * Cancelled too late, so it is charged in full.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => SessionKind::Cancelled,
        ]);
    }

    /**
     * Cancelled in time: nothing to pay, kept in the history so the slot is not forgotten.
     */
    public function waived(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => SessionKind::Cancelled,
            'payment_status' => PaymentStatus::Waived,
            'price' => 0,
        ]);
    }

    /**
     * The client did not show up — charged like a session, but no session was held.
     */
    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => SessionKind::NoShow,
        ]);
    }

    /**
     * Put the session on a given day, e.g. on('2026-09-30').
     */
    public function on(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
