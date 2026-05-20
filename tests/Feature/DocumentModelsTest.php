<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\RentalAgreement;
use App\Models\User;
use Database\Seeders\DocumentTemplateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_rental_agreement_can_own_generic_documents(): void
    {
        $template = DocumentTemplate::factory()->create([
            'document_type' => 'rental_agreement_contract',
            'status' => DocumentTemplate::STATUS_ACTIVE,
        ]);
        $agreement = RentalAgreement::factory()->create();
        $creator = User::factory()->create();

        $document = $agreement->documents()->create([
            'document_template_id' => $template->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_DRAFT,
            'title' => 'Wohnraummietvertrag',
            'metadata' => ['source' => 'manual'],
            'created_by_id' => $creator->id,
        ]);

        $document->load(['documentable', 'template', 'creator']);

        $this->assertTrue($document->documentable->is($agreement));
        $this->assertTrue($document->template->is($template));
        $this->assertTrue($document->creator->is($creator));
        $this->assertSame(['source' => 'manual'], $document->metadata);
        $this->assertTrue($agreement->documents()->first()->is($document));
    }

    public function test_document_versions_keep_snapshots_and_files(): void
    {
        $document = Document::factory()->create();
        $template = DocumentTemplate::factory()->create();
        $generator = User::factory()->create();

        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'document_template_id' => $template->id,
            'version_number' => 2,
            'status' => DocumentVersion::STATUS_GENERATED,
            'data_snapshot' => [
                'rental_agreement' => [
                    'rent_cold' => '900.00',
                ],
            ],
            'generated_by_id' => $generator->id,
        ]);

        $file = DocumentFile::factory()->create([
            'document_version_id' => $version->id,
            'file_type' => DocumentFile::TYPE_GENERATED_PDF,
        ]);

        $document->load(['latestVersion', 'versions.files']);
        $version->load(['document', 'template', 'generatedBy', 'files']);
        $file->load('version');

        $this->assertTrue($document->latestVersion->is($version));
        $this->assertCount(1, $document->versions);
        $this->assertTrue($version->document->is($document));
        $this->assertTrue($version->template->is($template));
        $this->assertTrue($version->generatedBy->is($generator));
        $this->assertSame([
            'rental_agreement' => [
                'rent_cold' => '900.00',
            ],
        ], $version->data_snapshot);
        $this->assertCount(1, $version->files);
        $this->assertTrue($file->version->is($version));
    }

    public function test_document_version_numbers_are_unique_per_document(): void
    {
        $document = Document::factory()->create();

        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
        ]);
    }

    public function test_document_template_seeder_creates_standard_rental_agreement_template(): void
    {
        $this->seed(DocumentTemplateSeeder::class);

        $this->assertDatabaseHas('document_templates', [
            'document_type' => 'rental_agreement_contract',
            'template_type' => 'residential',
            'locale' => 'de-DE',
            'version' => 1,
            'status' => DocumentTemplate::STATUS_ACTIVE,
            'name' => 'Wohnraummietvertrag Standard',
        ]);
    }
}
