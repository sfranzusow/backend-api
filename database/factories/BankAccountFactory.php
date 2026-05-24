<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => null,
            'account_holder' => fake()->name(),
            'iban' => 'DE'.fake()->numerify('####################'),
            'bic' => fake()->optional()->randomElement(['COLSDEDDXXX', 'DEUTDEFF', 'COBADEFFXXX']),
            'bank_name' => fake()->optional()->company(),
            'is_default' => false,
        ];
    }
}
