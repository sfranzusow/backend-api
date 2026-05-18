<?php

namespace Database\Factories;

use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'document_type' => fake()->unique()->bothify('document_type_####'),
            'template_type' => 'default',
            'locale' => 'de-DE',
            'version' => 1,
            'status' => fake()->randomElement(DocumentTemplate::statuses()),
            'content' => '<h1>{{ document.title }}</h1>',
            'placeholders' => [
                'landlord.name',
                'tenant.name',
                'property.address',
                'rental_agreement.date_from',
            ],
            'metadata' => [
                'description' => fake()->sentence(),
            ],
            'created_by_id' => User::factory(),
        ];
    }
}
