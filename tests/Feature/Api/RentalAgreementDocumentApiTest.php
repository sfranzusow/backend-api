<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentReminder;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\Property;
use App\Models\RentalAgreement;
use App\Models\User;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RentalAgreementDocumentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_cannot_access_rental_agreement_documents(): void
    {
        $agreement = RentalAgreement::factory()->create();
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
        ]);
        $reminder = DocumentReminder::factory()->create([
            'document_id' => $document->id,
        ]);

        $this->getJson('/api/rental-agreements/'.$agreement->id.'/documents')->assertUnauthorized();
        $this->postJson('/api/rental-agreements/'.$agreement->id.'/documents')->assertUnauthorized();
        $this->getJson('/api/documents/'.$document->id)->assertUnauthorized();
        $this->postJson('/api/documents/'.$document->id.'/generate')->assertUnauthorized();
        $this->postJson('/api/documents/'.$document->id.'/share')->assertUnauthorized();
        $this->postJson('/api/documents/'.$document->id.'/void')->assertUnauthorized();
        $this->getJson('/api/documents/'.$document->id.'/download')->assertUnauthorized();
        $this->postJson('/api/documents/'.$document->id.'/signed-upload')->assertUnauthorized();
        $this->getJson('/api/documents/'.$document->id.'/signed-download')->assertUnauthorized();
        $this->getJson('/api/documents/'.$document->id.'/reminders')->assertUnauthorized();
        $this->postJson('/api/documents/'.$document->id.'/reminders')->assertUnauthorized();
        $this->patchJson('/api/document-reminders/'.$reminder->id)->assertUnauthorized();
        $this->deleteJson('/api/document-reminders/'.$reminder->id)->assertUnauthorized();
    }

    public function test_landlord_can_create_document_for_own_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $template = DocumentTemplate::factory()->create([
            'document_type' => 'rental_agreement_contract',
            'status' => DocumentTemplate::STATUS_ACTIVE,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/rental-agreements/'.$agreement->id.'/documents', [
                'document_template_id' => $template->id,
                'document_type' => 'rental_agreement_contract',
                'title' => 'Wohnraummietvertrag',
                'metadata' => [
                    'source' => 'manual',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.documentable_type', 'RentalAgreement')
            ->assertJsonPath('data.documentable_id', $agreement->id)
            ->assertJsonPath('data.document_template_id', $template->id)
            ->assertJsonPath('data.document_type', 'rental_agreement_contract')
            ->assertJsonPath('data.status', Document::STATUS_DRAFT)
            ->assertJsonPath('data.title', 'Wohnraummietvertrag')
            ->assertJsonPath('data.metadata.source', 'manual')
            ->assertJsonPath('data.created_by_id', $user->id)
            ->assertJsonPath('data.template.id', $template->id);

        $this->assertDatabaseHas('documents', [
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_template_id' => $template->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_DRAFT,
            'title' => 'Wohnraummietvertrag',
            'created_by_id' => $user->id,
        ]);
    }

    public function test_tenant_can_list_own_rental_agreement_documents(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);
        $matchingDocument = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
        ]);

        Document::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements/'.$agreement->id.'/documents')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingDocument->id);
    }

    public function test_tenant_cannot_create_document_for_own_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/rental-agreements/'.$agreement->id.'/documents', [
                'document_type' => 'rental_agreement_contract',
                'title' => 'Wohnraummietvertrag',
            ])
            ->assertForbidden();
    }

    public function test_landlord_cannot_create_document_for_unmanaged_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $agreement = RentalAgreement::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/rental-agreements/'.$agreement->id.'/documents', [
                'document_type' => 'rental_agreement_contract',
                'title' => 'Wohnraummietvertrag',
            ])
            ->assertForbidden();
    }

    public function test_landlord_can_show_document_for_own_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/documents/'.$document->id)
            ->assertSuccessful()
            ->assertJsonPath('data.id', $document->id)
            ->assertJsonPath('data.documentable_id', $agreement->id);
    }

    public function test_landlord_can_manage_document_reminders_for_own_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $tenant = User::factory()->create();
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/reminders', [
                'title' => 'Unterschrift prüfen',
                'notes' => 'Scan liegt beim Verwalter.',
                'due_at' => '2026-06-15T10:00:00+00:00',
                'remind_at' => '2026-06-10T10:00:00+00:00',
                'assigned_to_id' => $tenant->id,
                'metadata' => [
                    'channel' => 'email',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Unterschrift prüfen')
            ->assertJsonPath('data.status', DocumentReminder::STATUS_PENDING)
            ->assertJsonPath('data.assigned_to_id', $tenant->id)
            ->assertJsonPath('data.created_by_id', $user->id)
            ->assertJsonPath('data.metadata.channel', 'email');

        $reminderId = $response->json('data.id');

        $this->assertDatabaseHas('document_reminders', [
            'id' => $reminderId,
            'document_id' => $document->id,
            'title' => 'Unterschrift prüfen',
            'status' => DocumentReminder::STATUS_PENDING,
            'assigned_to_id' => $tenant->id,
            'created_by_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/documents/'.$document->id.'/reminders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reminderId);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/document-reminders/'.$reminderId, [
                'status' => DocumentReminder::STATUS_DONE,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', DocumentReminder::STATUS_DONE);

        $this->assertDatabaseHas('document_reminders', [
            'id' => $reminderId,
            'status' => DocumentReminder::STATUS_DONE,
        ]);
        $this->assertNotNull(DocumentReminder::query()->findOrFail($reminderId)->completed_at);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/document-reminders/'.$reminderId)
            ->assertNoContent();

        $this->assertDatabaseMissing('document_reminders', [
            'id' => $reminderId,
        ]);
    }

    public function test_tenant_can_view_but_not_create_document_reminders_for_own_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
        ]);
        $reminder = DocumentReminder::factory()->create([
            'document_id' => $document->id,
            'title' => 'Unterschrift fällig',
            'assigned_to_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/documents/'.$document->id.'/reminders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reminder->id);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/reminders', [
                'title' => 'Eigene Erinnerung',
                'due_at' => '2026-06-15T10:00:00+00:00',
            ])
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/document-reminders/'.$reminder->id, [
                'status' => DocumentReminder::STATUS_DONE,
            ])
            ->assertForbidden();
    }

    public function test_document_reminder_validates_reminder_before_due_date(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/reminders', [
                'title' => 'Unterschrift prüfen',
                'due_at' => '2026-06-10T10:00:00+00:00',
                'remind_at' => '2026-06-15T10:00:00+00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['remind_at']);
    }

    public function test_user_cannot_show_document_without_rental_agreement_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'document_type' => 'rental_agreement_contract',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/documents/'.$document->id)
            ->assertForbidden();
    }

    public function test_document_template_must_match_document_type(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $template = DocumentTemplate::factory()->create([
            'document_type' => 'handover_protocol',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/rental-agreements/'.$agreement->id.'/documents', [
                'document_template_id' => $template->id,
                'document_type' => 'rental_agreement_contract',
                'title' => 'Wohnraummietvertrag',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_template_id']);
    }

    public function test_landlord_can_generate_and_download_pdf_for_own_rental_agreement_document(): void
    {
        Storage::fake($this->documentsDisk());

        $user = User::factory()->create(['name' => 'Erika Vermieter']);
        $user->assignRole(RoleName::Landlord->value);
        $tenant = User::factory()->create(['name' => 'Max Mieter']);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'tenant_id' => $tenant->id,
            'date_from' => '2026-06-01',
            'date_to' => null,
            'rent_cold' => '900.00',
            'rent_warm' => '1100.00',
            'deposit' => '2700.00',
            'currency' => 'EUR',
        ]);
        $template = DocumentTemplate::factory()->create([
            'document_type' => 'rental_agreement_contract',
            'status' => DocumentTemplate::STATUS_ACTIVE,
            'content' => '<h1>{{ document.title }}</h1><p>{{ landlord.name }}</p><p>{{ tenant.name }}</p><p>{{ rental_agreement.rent_cold }}</p>',
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_template_id' => $template->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_DRAFT,
            'title' => 'Wohnraummietvertrag',
            'created_by_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/generate')
            ->assertCreated()
            ->assertJsonPath('data.status', Document::STATUS_GENERATED)
            ->assertJsonPath('data.latest_version.status', DocumentVersion::STATUS_GENERATED)
            ->assertJsonPath('data.latest_version.version_number', 1)
            ->assertJsonPath('data.latest_version.generated_by_id', $user->id)
            ->assertJsonPath('data.latest_version.data_snapshot.landlord.name', 'Erika Vermieter')
            ->assertJsonPath('data.latest_version.data_snapshot.tenant.name', 'Max Mieter')
            ->assertJsonPath('data.latest_version.data_snapshot.rental_agreement.rent_cold', '900.00')
            ->assertJsonPath('data.latest_version.content_snapshot', '<h1>Wohnraummietvertrag</h1><p>Erika Vermieter</p><p>Max Mieter</p><p>900.00</p>')
            ->assertJsonCount(1, 'data.latest_version.files');

        $filePath = $response->json('data.latest_version.files.0.path');

        Storage::disk($this->documentsDisk())->assertExists($filePath);
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk($this->documentsDisk())->get($filePath));

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => Document::STATUS_GENERATED,
        ]);
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $document->id,
            'version_number' => 1,
            'status' => DocumentVersion::STATUS_GENERATED,
            'generated_by_id' => $user->id,
        ]);
        $this->assertDatabaseHas('document_files', [
            'file_type' => DocumentFile::TYPE_GENERATED_PDF,
            'disk' => $this->documentsDisk(),
            'path' => $filePath,
            'mime_type' => 'application/pdf',
        ]);

        $download = $this->actingAs($user, 'sanctum')
            ->get('/api/documents/'.$document->id.'/download')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-1.4', $download->streamedContent());
    }

    public function test_tenant_cannot_generate_document_for_own_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/generate')
            ->assertForbidden();
    }

    public function test_landlord_can_generate_document_with_seeded_standard_template(): void
    {
        Storage::fake($this->documentsDisk());
        $this->seed(DocumentTemplateSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_template_id' => null,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/generate')
            ->assertCreated()
            ->assertJsonPath('data.status', Document::STATUS_GENERATED)
            ->assertJsonPath('data.template.name', 'Wohnraummietvertrag Standard')
            ->assertJsonPath('data.latest_version.template_snapshot.name', 'Wohnraummietvertrag Standard')
            ->assertJsonCount(1, 'data.latest_version.files');

        Storage::disk($this->documentsDisk())->assertExists($response->json('data.latest_version.files.0.path'));
    }

    public function test_regenerating_document_voids_previous_generated_version(): void
    {
        Storage::fake($this->documentsDisk());

        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $template = DocumentTemplate::factory()->create([
            'document_type' => 'rental_agreement_contract',
            'status' => DocumentTemplate::STATUS_ACTIVE,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_template_id' => $template->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_GENERATED,
        ]);
        $previousVersion = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'document_template_id' => $template->id,
            'version_number' => 1,
            'status' => DocumentVersion::STATUS_GENERATED,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/generate')
            ->assertCreated()
            ->assertJsonPath('data.status', Document::STATUS_GENERATED)
            ->assertJsonPath('data.latest_version.version_number', 2)
            ->assertJsonPath('data.latest_version.status', DocumentVersion::STATUS_GENERATED);

        $this->assertDatabaseHas('document_versions', [
            'id' => $previousVersion->id,
            'status' => DocumentVersion::STATUS_VOID,
        ]);
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $document->id,
            'version_number' => 2,
            'status' => DocumentVersion::STATUS_GENERATED,
        ]);
    }

    public function test_landlord_can_share_generated_document(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_GENERATED,
        ]);
        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'status' => DocumentVersion::STATUS_GENERATED,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/share')
            ->assertOk()
            ->assertJsonPath('data.status', Document::STATUS_SHARED)
            ->assertJsonPath('data.latest_version.status', DocumentVersion::STATUS_SHARED);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => Document::STATUS_SHARED,
        ]);
        $this->assertDatabaseHas('document_versions', [
            'id' => $version->id,
            'status' => DocumentVersion::STATUS_SHARED,
        ]);
    }

    public function test_tenant_cannot_share_own_rental_agreement_document(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_GENERATED,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/share')
            ->assertForbidden();
    }

    public function test_landlord_can_void_draft_document_without_version(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_DRAFT,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/void')
            ->assertOk()
            ->assertJsonPath('data.status', Document::STATUS_VOID)
            ->assertJsonMissingPath('data.latest_version.id');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => Document::STATUS_VOID,
        ]);
    }

    public function test_landlord_can_void_generated_document_and_downloads_are_unavailable(): void
    {
        Storage::fake($this->documentsDisk());

        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_SIGNED_UPLOADED,
        ]);
        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'status' => DocumentVersion::STATUS_SIGNED_UPLOADED,
        ]);
        Storage::disk($this->documentsDisk())->put('documents/test-generated.pdf', '%PDF-1.4 generated');
        Storage::disk($this->documentsDisk())->put('documents/test-signed.pdf', '%PDF-1.4 signed');
        DocumentFile::factory()->create([
            'document_version_id' => $version->id,
            'file_type' => DocumentFile::TYPE_GENERATED_PDF,
            'disk' => $this->documentsDisk(),
            'path' => 'documents/test-generated.pdf',
        ]);
        DocumentFile::factory()->create([
            'document_version_id' => $version->id,
            'file_type' => DocumentFile::TYPE_SIGNED_UPLOAD,
            'disk' => $this->documentsDisk(),
            'path' => 'documents/test-signed.pdf',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/void')
            ->assertOk()
            ->assertJsonPath('data.status', Document::STATUS_VOID)
            ->assertJsonPath('data.latest_version.status', DocumentVersion::STATUS_VOID);

        $this->assertDatabaseHas('document_versions', [
            'id' => $version->id,
            'status' => DocumentVersion::STATUS_VOID,
        ]);

        $this->actingAs($user, 'sanctum')
            ->get('/api/documents/'.$document->id.'/download')
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->get('/api/documents/'.$document->id.'/signed-download')
            ->assertNotFound();
    }

    public function test_void_document_cannot_be_generated_or_signed_again(): void
    {
        Storage::fake($this->documentsDisk());

        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $template = DocumentTemplate::factory()->create([
            'document_type' => 'rental_agreement_contract',
            'status' => DocumentTemplate::STATUS_ACTIVE,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_template_id' => $template->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_VOID,
        ]);
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'status' => DocumentVersion::STATUS_VOID,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/generate')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/signed-upload', [
                'file' => UploadedFile::fake()->create('signed-contract.pdf', 120, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document']);
    }

    public function test_landlord_can_upload_and_download_signed_document_for_own_rental_agreement(): void
    {
        Storage::fake($this->documentsDisk());

        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_GENERATED,
        ]);
        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'status' => DocumentVersion::STATUS_GENERATED,
        ]);
        $file = UploadedFile::fake()->createWithContent('signed-contract.pdf', '%PDF-1.4 signed contract');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/signed-upload', [
                'file' => $file,
                'metadata' => [
                    'source' => 'scan',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', Document::STATUS_SIGNED_UPLOADED)
            ->assertJsonPath('data.latest_version.status', DocumentVersion::STATUS_SIGNED_UPLOADED)
            ->assertJsonPath('data.latest_version.files.0.file_type', DocumentFile::TYPE_SIGNED_UPLOAD)
            ->assertJsonPath('data.latest_version.files.0.original_name', 'signed-contract.pdf')
            ->assertJsonPath('data.latest_version.files.0.mime_type', 'application/pdf')
            ->assertJsonPath('data.latest_version.files.0.uploaded_by_id', $user->id)
            ->assertJsonPath('data.latest_version.files.0.metadata.source', 'scan');

        $filePath = $response->json('data.latest_version.files.0.path');

        Storage::disk($this->documentsDisk())->assertExists($filePath);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => Document::STATUS_SIGNED_UPLOADED,
        ]);
        $this->assertDatabaseHas('document_versions', [
            'id' => $version->id,
            'status' => DocumentVersion::STATUS_SIGNED_UPLOADED,
        ]);
        $this->assertDatabaseHas('document_files', [
            'document_version_id' => $version->id,
            'file_type' => DocumentFile::TYPE_SIGNED_UPLOAD,
            'path' => $filePath,
            'original_name' => 'signed-contract.pdf',
            'uploaded_by_id' => $user->id,
        ]);

        $download = $this->actingAs($user, 'sanctum')
            ->get('/api/documents/'.$document->id.'/signed-download')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertSame('%PDF-1.4 signed contract', $download->streamedContent());
    }

    public function test_tenant_can_upload_signed_document_for_own_rental_agreement(): void
    {
        Storage::fake($this->documentsDisk());

        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_GENERATED,
        ]);
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'status' => DocumentVersion::STATUS_GENERATED,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/signed-upload', [
                'file' => UploadedFile::fake()->create('signed-contract.pdf', 120, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', Document::STATUS_SIGNED_UPLOADED)
            ->assertJsonPath('data.latest_version.status', DocumentVersion::STATUS_SIGNED_UPLOADED);

        Storage::disk($this->documentsDisk())->assertExists($response->json('data.latest_version.files.0.path'));
    }

    public function test_signed_document_upload_requires_existing_document_version(): void
    {
        Storage::fake($this->documentsDisk());

        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/signed-upload', [
                'file' => UploadedFile::fake()->create('signed-contract.pdf', 120, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document']);
    }

    public function test_signed_document_upload_validates_file_type(): void
    {
        Storage::fake($this->documentsDisk());

        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_GENERATED,
        ]);
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'status' => DocumentVersion::STATUS_GENERATED,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/signed-upload', [
                'file' => UploadedFile::fake()->create('malware.exe', 120, 'application/octet-stream'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    private function propertyManagedBy(User $landlord): Property
    {
        $property = Property::factory()->create();
        $property->users()->attach($landlord->id, ['role' => RoleName::Landlord->value]);

        return $property;
    }

    private function documentsDisk(): string
    {
        $disk = config('documents.disk');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }
}
