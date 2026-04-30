<?php

namespace Database\Factories;

use App\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'street' => fake()->streetName(),
            'house_number' => (string) fake()->numberBetween(1, 300),
            'zip_code' => fake()->postcode(),
            'city' => fake()->city(),
            'country' => 'DE',
            'latitude' => fake()->optional()->latitude(47.2, 55.1),
            'longitude' => fake()->optional()->longitude(5.9, 15.0),
        ];
    }
}
