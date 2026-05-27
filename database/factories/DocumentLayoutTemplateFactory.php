<?php

namespace Database\Factories;

use App\Models\DocumentLayoutTemplate;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentLayoutTemplate>
 */
class DocumentLayoutTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => User::class,
            'owner_id' => User::factory(),
            'name' => fake()->words(3, true),
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            'locale' => 'de-DE',
            'version' => 1,
            'status' => fake()->randomElement(DocumentLayoutTemplate::statuses()),
            'header_enabled' => fake()->boolean(),
            'footer_enabled' => fake()->boolean(),
            'page_numbers_enabled' => true,
            'header_content' => '<p>{{ document.title }}</p>',
            'footer_content' => '<p>{{ landlord.name }}</p>',
            'header_banner_path' => null,
            'footer_banner_path' => null,
            'placeholders' => [
                'document.title',
                'landlord.name',
            ],
            'metadata' => [
                'source' => 'factory',
            ],
            'created_by_id' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentLayoutTemplate::STATUS_ACTIVE,
        ]);
    }
}
