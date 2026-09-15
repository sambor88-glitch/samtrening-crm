<?php

namespace Database\Factories;

use App\Domain\Billing\Models\Prepayment;
use App\Domain\Clients\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A row without the pool worked out — tests that care which sessions it pays for go through
 * Billing\Actions\RecordPrepayment instead.
 *
 * @extends Factory<Prepayment>
 */
class PrepaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Prepayment>
     */
    protected $model = Prepayment::class;

    /**
     * Define the model's default state: a thousand złoty paid today.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'amount' => 100000,
            'paid_on' => now()->toDateString(),
        ];
    }
}
