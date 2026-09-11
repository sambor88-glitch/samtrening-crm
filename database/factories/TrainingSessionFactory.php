<?php

namespace Database\Factories;

use App\Domain\Clients\Models\Client;
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
            'kind' => 'completed',
            'payment_status' => 'balance',
        ];
    }

    /**
     * A session the client has already paid for.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
        ]);
    }
}
