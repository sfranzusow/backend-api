<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentLayoutTemplate;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'document_template_id' => DocumentTemplate::factory(),
            'document_layout_template_id' => DocumentLayoutTemplate::factory(),
            'version_number' => 1,
            'status' => fake()->randomElement(DocumentVersion::statuses()),
            'title' => fake()->sentence(3),
            'content_snapshot' => '<h1>Snapshot</h1>',
            'template_snapshot' => [
                'name' => fake()->words(3, true),
                'version' => 1,
            ],
            'layout_snapshot' => [
                'name' => fake()->words(3, true),
                'version' => 1,
            ],
            'data_snapshot' => [
                'rental_agreement' => [
                    'date_from' => fake()->date(),
                ],
            ],
            'metadata' => [
                'source' => 'factory',
            ],
            'generated_by_id' => User::factory(),
            'generated_at' => now(),
        ];
    }
}
