<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\RentalAgreement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => RentalAgreement::factory(),
            'document_template_id' => DocumentTemplate::factory(),
            'document_type' => 'rental_agreement_contract',
            'status' => fake()->randomElement(Document::statuses()),
            'title' => fake()->sentence(3),
            'metadata' => [
                'source' => 'factory',
            ],
            'created_by_id' => User::factory(),
        ];
    }
}
