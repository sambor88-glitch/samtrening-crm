<?php

namespace Database\Factories;

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Client>
     */
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trainer_id' => User::factory(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('+48 ### ### ###'),
            'email' => fake()->unique()->safeEmail(),
            'rate' => fake()->randomElement([15000, 18000, 20000, 22000]),
            'goal' => fake()->sentence(),
            'consent_given' => true,
            'consent_date' => now()->subMonths(2)->toDateString(),
        ];
    }

    /**
     * A client moved to the archive.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived' => true,
        ]);
    }
}
