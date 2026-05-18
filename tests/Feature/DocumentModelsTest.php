<?php

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\RentalAgreement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a rental agreement can own generic documents', function () {
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

    expect($document->documentable->is($agreement))->toBeTrue()
        ->and($document->template->is($template))->toBeTrue()
        ->and($document->creator->is($creator))->toBeTrue()
        ->and($document->metadata)->toBe(['source' => 'manual'])
        ->and($agreement->documents()->first()->is($document))->toBeTrue();
});

test('document versions keep snapshots and files', function () {
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

    expect($document->latestVersion->is($version))->toBeTrue()
        ->and($document->versions)->toHaveCount(1)
        ->and($version->document->is($document))->toBeTrue()
        ->and($version->template->is($template))->toBeTrue()
        ->and($version->generatedBy->is($generator))->toBeTrue()
        ->and($version->data_snapshot)->toBe([
            'rental_agreement' => [
                'rent_cold' => '900.00',
            ],
        ])
        ->and($version->files)->toHaveCount(1)
        ->and($file->version->is($version))->toBeTrue();
});

test('document version numbers are unique per document', function () {
    $document = Document::factory()->create();

    DocumentVersion::factory()->create([
        'document_id' => $document->id,
        'version_number' => 1,
    ]);

    expect(fn () => DocumentVersion::factory()->create([
        'document_id' => $document->id,
        'version_number' => 1,
    ]))->toThrow(QueryException::class);
});
