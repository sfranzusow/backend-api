<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\RentalAgreement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalAgreement>
 */
class RentalAgreementFactory extends Factory
{
    public function definition(): array
    {
        $dateFrom = fake()->dateTimeBetween('-2 years', 'now');
        $dateTo = fake()->optional()->dateTimeBetween($dateFrom, '+2 years');

        return [
            'property_id' => Property::factory(),
            'landlord_id' => User::factory(),
            'tenant_id' => User::factory(),
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo?->format('Y-m-d'),
            'rent_cold' => fake()->randomFloat(2, 300, 5000),
            'rent_warm' => fake()->optional()->randomFloat(2, 400, 7000),
            'service_charges' => fake()->optional()->randomFloat(2, 50, 1200),
            'deposit' => fake()->optional()->randomFloat(2, 0, 20000),
            'currency' => 'EUR',
            'status' => fake()->randomElement(['draft', 'active', 'terminated', 'ended']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
