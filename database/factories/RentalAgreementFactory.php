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
            'bank_account_id' => null,
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo?->format('Y-m-d'),
            'rent_cold' => fake()->randomFloat(2, 300, 5000),
            'rent_warm' => fake()->optional()->randomFloat(2, 400, 7000),
            'service_charges' => fake()->optional()->randomFloat(2, 50, 1200),
            'deposit' => fake()->optional()->randomFloat(2, 0, 20000),
            'currency' => 'EUR',
            'lease_subject_description' => null,
            'additional_spaces' => null,
            'shared_facilities' => null,
            'fixed_term_reason' => null,
            'handover_due_at' => null,
            'operating_costs_allocation_key' => null,
            'renovation_condition' => null,
            'renovation_condition_notes' => null,
            'cosmetic_repairs_agreement' => null,
            'small_repairs_single_limit' => null,
            'small_repairs_annual_limit' => null,
            'handover_protocol_attached' => false,
            'house_rules_attached' => false,
            'operating_costs_overview_attached' => false,
            'energy_certificate_attached' => false,
            'self_disclosure_attached' => false,
            'other_attachments' => null,
            'individual_agreements' => null,
            'status' => fake()->randomElement(['draft', 'active', 'terminated', 'ended']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
