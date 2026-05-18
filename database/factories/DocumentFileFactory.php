<?php

namespace Database\Factories;

use App\Models\DocumentFile;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentFile>
 */
class DocumentFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = fake()->uuid();

        return [
            'document_version_id' => DocumentVersion::factory(),
            'file_type' => fake()->randomElement(DocumentFile::fileTypes()),
            'disk' => 'local',
            'path' => 'documents/'.$uuid.'.pdf',
            'original_name' => $uuid.'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(10_000, 2_000_000),
            'checksum' => hash('sha256', $uuid),
            'metadata' => [
                'source' => 'factory',
            ],
            'uploaded_by_id' => User::factory(),
            'uploaded_at' => now(),
        ];
    }
}
