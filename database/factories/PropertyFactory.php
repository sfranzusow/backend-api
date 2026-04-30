<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'address_id' => Address::factory(),
            'unit_number' => fake()->optional()->bothify('Top ##'),
            'type' => fake()->randomElement(['apartment', 'office', 'penthouse', 'studio']),
            'area_living' => fake()->randomFloat(2, 20, 350),
            'rooms' => fake()->numberBetween(1, 8),
            'floor' => fake()->optional()->numberBetween(0, 20),
            'build_year' => fake()->optional()->numberBetween(1900, (int) date('Y')),
            'energy_class' => fake()->optional()->randomElement(['A', 'B', 'C', 'D', 'E', 'F', 'G']),
            'price' => fake()->optional()->randomFloat(2, 50000, 1200000),
            'features' => fake()->optional()->randomElements(['balcony', 'parking', 'elevator', 'garden'], fake()->numberBetween(1, 3)),
            'status' => fake()->randomElement(['available', 'rented', 'sold']),
        ];
    }
}
