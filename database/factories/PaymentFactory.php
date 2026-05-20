<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\RentalAgreement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payable_type' => RentalAgreement::class,
            'payable_id' => RentalAgreement::factory(),
            'type' => fake()->randomElement(Payment::types()),
            'direction' => Payment::DIRECTION_INCOMING,
            'status' => Payment::STATUS_PENDING,
            'amount' => fake()->randomFloat(2, 50, 5000),
            'currency' => 'EUR',
            'due_date' => fake()->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
            'paid_at' => null,
            'payer_id' => User::factory(),
            'payee_id' => User::factory(),
            'description' => fake()->optional()->sentence(),
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function outgoing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'direction' => Payment::DIRECTION_OUTGOING,
        ]);
    }
}
