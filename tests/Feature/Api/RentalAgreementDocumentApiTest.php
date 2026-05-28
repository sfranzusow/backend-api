<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\BankAccount;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentLayoutTemplate;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reminder;
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
        $reminder = Reminder::factory()->create([
            'remindable_type' => Document::class,
            'remindable_id' => $document->id,
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
        $this->patchJson('/api/reminders/'.$reminder->id)->assertUnauthorized();
        $this->deleteJson('/api/reminders/'.$reminder->id)->assertUnauthorized();
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

    public function test_tenant_only_sees_shared_or_signed_documents_for_own_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);
        $sharedDocument = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_SHARED,
            'title' => 'Freigegebenes Dokument',
        ]);
        $signedDocument = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_SIGNED_UPLOADED,
            'title' => 'Unterschriebene Version',
        ]);
        $generatedDocument = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_GENERATED,
            'title' => 'Nur erzeugtes Dokument',
        ]);

        Document::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements/'.$agreement->id.'/documents')
            ->assertSuccessful()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $sharedDocument->id])
            ->assertJsonFragment(['id' => $signedDocument->id])
            ->assertJsonMissing(['title' => $generatedDocument->title]);
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

    public function test_landlord_can_manage_reminders_for_own_rental_agreement(): void
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
            ->assertJsonPath('data.remindable_type', 'Document')
            ->assertJsonPath('data.remindable_id', $document->id)
            ->assertJsonPath('data.status', Reminder::STATUS_PENDING)
            ->assertJsonPath('data.assigned_to_id', $tenant->id)
            ->assertJsonPath('data.created_by_id', $user->id)
            ->assertJsonPath('data.metadata.channel', 'email')
            ->assertJsonPath('data.actions.update', true)
            ->assertJsonPath('data.actions.delete', true)
            ->assertJsonPath('data.actions.mark_done', true);

        $reminderId = $response->json('data.id');

        $this->assertDatabaseHas('reminders', [
            'id' => $reminderId,
            'remindable_type' => Document::class,
            'remindable_id' => $document->id,
            'title' => 'Unterschrift prüfen',
            'status' => Reminder::STATUS_PENDING,
            'assigned_to_id' => $tenant->id,
            'created_by_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/documents/'.$document->id.'/reminders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reminderId)
            ->assertJsonPath('data.0.actions.update', true)
            ->assertJsonPath('data.0.actions.delete', true)
            ->assertJsonPath('data.0.actions.mark_done', true);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/reminders/'.$reminderId, [
                'status' => Reminder::STATUS_DONE,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Reminder::STATUS_DONE)
            ->assertJsonPath('data.actions.update', true)
            ->assertJsonPath('data.actions.delete', true)
            ->assertJsonPath('data.actions.mark_done', false);

        $this->assertDatabaseHas('reminders', [
            'id' => $reminderId,
            'status' => Reminder::STATUS_DONE,
        ]);
        $this->assertNotNull(Reminder::query()->findOrFail($reminderId)->completed_at);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/reminders/'.$reminderId)
            ->assertNoContent();

        $this->assertDatabaseMissing('reminders', [
            'id' => $reminderId,
        ]);
    }

    public function test_landlord_can_manage_reminders_for_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/rental-agreements/'.$agreement->id.'/reminders', [
                'title' => 'Kautionseingang prüfen',
                'due_at' => '2026-06-15T10:00:00+00:00',
                'remind_at' => '2026-06-10T10:00:00+00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.remindable_type', 'RentalAgreement')
            ->assertJsonPath('data.remindable_id', $agreement->id)
            ->assertJsonPath('data.title', 'Kautionseingang prüfen')
            ->assertJsonPath('data.status', Reminder::STATUS_PENDING)
            ->assertJsonPath('data.actions.mark_done', true);

        $reminderId = $response->json('data.id');

        $this->assertDatabaseHas('reminders', [
            'id' => $reminderId,
            'remindable_type' => RentalAgreement::class,
            'remindable_id' => $agreement->id,
            'title' => 'Kautionseingang prüfen',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements/'.$agreement->id.'/reminders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reminderId)
            ->assertJsonPath('data.0.remindable_type', 'RentalAgreement');
    }

    public function test_landlord_can_manage_reminders_for_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $payment = Payment::factory()->create([
            'payable_type' => RentalAgreement::class,
            'payable_id' => $agreement->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments/'.$payment->id.'/reminders', [
                'title' => 'Zahlungseingang kontrollieren',
                'due_at' => '2026-06-15T10:00:00+00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.remindable_type', 'Payment')
            ->assertJsonPath('data.remindable_id', $payment->id)
            ->assertJsonPath('data.title', 'Zahlungseingang kontrollieren')
            ->assertJsonPath('data.actions.mark_done', true);

        $reminderId = $response->json('data.id');

        $this->assertDatabaseHas('reminders', [
            'id' => $reminderId,
            'remindable_type' => Payment::class,
            'remindable_id' => $payment->id,
            'title' => 'Zahlungseingang kontrollieren',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payments/'.$payment->id.'/reminders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reminderId)
            ->assertJsonPath('data.0.remindable_type', 'Payment');
    }

    public function test_admin_reminder_response_exposes_management_actions(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);
        $document = Document::factory()->create();
        $reminder = Reminder::factory()->create([
            'remindable_type' => Document::class,
            'remindable_id' => $document->id,
            'status' => Reminder::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/documents/'.$document->id.'/reminders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reminder->id)
            ->assertJsonPath('data.0.actions.update', true)
            ->assertJsonPath('data.0.actions.delete', true)
            ->assertJsonPath('data.0.actions.mark_done', true);
    }

    public function test_reminder_response_exposes_display_status_from_dates(): void
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

        Reminder::factory()->create([
            'remindable_type' => Document::class,
            'remindable_id' => $document->id,
            'title' => 'Noch nicht erinnern',
            'status' => Reminder::STATUS_PENDING,
            'remind_at' => '2026-06-15T10:00:00+00:00',
            'due_at' => '2026-06-20T10:00:00+00:00',
        ]);
        Reminder::factory()->create([
            'remindable_type' => Document::class,
            'remindable_id' => $document->id,
            'title' => 'Jetzt erinnern',
            'status' => Reminder::STATUS_PENDING,
            'remind_at' => '2026-06-10T10:00:00+00:00',
            'due_at' => '2026-06-20T10:00:00+00:00',
        ]);
        Reminder::factory()->create([
            'remindable_type' => Document::class,
            'remindable_id' => $document->id,
            'title' => 'Versaeumt',
            'status' => Reminder::STATUS_PENDING,
            'remind_at' => '2026-06-01T10:00:00+00:00',
            'due_at' => '2026-06-10T10:00:00+00:00',
        ]);
        Reminder::factory()->done()->create([
            'remindable_type' => Document::class,
            'remindable_id' => $document->id,
            'title' => 'Erledigt',
            'due_at' => '2026-06-10T10:00:00+00:00',
        ]);
        Reminder::factory()->cancelled()->create([
            'remindable_type' => Document::class,
            'remindable_id' => $document->id,
            'title' => 'Abgebrochen',
            'due_at' => '2026-06-10T10:00:00+00:00',
        ]);

        $this->travelTo('2026-06-12T10:00:00+00:00', function () use ($user, $document): void {
            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/documents/'.$document->id.'/reminders')
                ->assertOk()
                ->assertJsonCount(5, 'data');

            $reminders = collect($response->json('data'))->keyBy('title');

            $this->assertSame(Reminder::DISPLAY_STATUS_OPEN, $reminders['Noch nicht erinnern']['display_status']);
            $this->assertSame(Reminder::DISPLAY_STATUS_REMINDER_DUE, $reminders['Jetzt erinnern']['display_status']);
            $this->assertSame(Reminder::DISPLAY_STATUS_OVERDUE, $reminders['Versaeumt']['display_status']);
            $this->assertSame(Reminder::STATUS_DONE, $reminders['Erledigt']['display_status']);
            $this->assertSame(Reminder::STATUS_CANCELLED, $reminders['Abgebrochen']['display_status']);
        });
    }

    public function test_tenant_can_view_but_not_create_reminders_for_own_rental_agreement(): void
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
            'status' => Document::STATUS_SHARED,
        ]);
        $reminder = Reminder::factory()->create([
            'remindable_type' => Document::class,
            'remindable_id' => $document->id,
            'title' => 'Unterschrift fällig',
            'assigned_to_id' => $user->id,
        ]);
        $landlordReminder = Reminder::factory()->create([
            'remindable_type' => Document::class,
            'remindable_id' => $document->id,
            'title' => 'Interne Wiedervorlage',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/documents/'.$document->id.'/reminders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reminder->id)
            ->assertJsonPath('data.0.actions.update', false)
            ->assertJsonPath('data.0.actions.delete', false)
            ->assertJsonPath('data.0.actions.mark_done', false)
            ->assertJsonMissing(['title' => $landlordReminder->title]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/documents/'.$document->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.reminders')
            ->assertJsonPath('data.reminders.0.id', $reminder->id)
            ->assertJsonPath('data.reminders.0.actions.update', false)
            ->assertJsonPath('data.reminders.0.actions.delete', false)
            ->assertJsonPath('data.reminders.0.actions.mark_done', false)
            ->assertJsonMissing(['title' => $landlordReminder->title]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/reminders', [
                'title' => 'Eigene Erinnerung',
                'due_at' => '2026-06-15T10:00:00+00:00',
            ])
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/reminders/'.$reminder->id, [
                'status' => Reminder::STATUS_DONE,
            ])
            ->assertForbidden();
    }

    public function test_tenant_document_response_hides_internal_fields_and_exposes_allowed_actions(): void
    {
        Storage::fake($this->documentsDisk());

        $landlord = User::factory()->create();
        $tenant = User::factory()->create();
        $tenant->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
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
            'status' => Document::STATUS_SHARED,
            'metadata' => [
                'internal_note' => 'nur Vermieter',
            ],
        ]);
        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'document_template_id' => $template->id,
            'version_number' => 1,
            'status' => DocumentVersion::STATUS_SHARED,
            'metadata' => [
                'renderer' => 'basic_pdf',
            ],
            'generated_by_id' => $landlord->id,
        ]);
        Storage::disk($this->documentsDisk())->put('documents/shared-generated.pdf', '%PDF-1.4 shared');
        DocumentFile::factory()->create([
            'document_version_id' => $version->id,
            'file_type' => DocumentFile::TYPE_GENERATED_PDF,
            'disk' => $this->documentsDisk(),
            'path' => 'documents/shared-generated.pdf',
            'checksum' => hash('sha256', 'shared'),
            'metadata' => [
                'storage' => 'private',
            ],
            'uploaded_by_id' => $landlord->id,
        ]);

        $this->actingAs($tenant, 'sanctum')
            ->getJson('/api/documents/'.$document->id)
            ->assertOk()
            ->assertJsonPath('data.actions.generate', false)
            ->assertJsonPath('data.actions.share', false)
            ->assertJsonPath('data.actions.void', false)
            ->assertJsonPath('data.actions.download', true)
            ->assertJsonPath('data.actions.upload_signed', true)
            ->assertJsonPath('data.actions.download_signed', false)
            ->assertJsonPath('data.actions.create_reminder', false)
            ->assertJsonMissingPath('data.document_template_id')
            ->assertJsonMissingPath('data.metadata')
            ->assertJsonMissingPath('data.template')
            ->assertJsonMissingPath('data.created_by_id')
            ->assertJsonMissingPath('data.creator')
            ->assertJsonMissingPath('data.latest_version.document_template_id')
            ->assertJsonMissingPath('data.latest_version.content_snapshot')
            ->assertJsonMissingPath('data.latest_version.template_snapshot')
            ->assertJsonMissingPath('data.latest_version.data_snapshot')
            ->assertJsonMissingPath('data.latest_version.metadata')
            ->assertJsonMissingPath('data.latest_version.generated_by_id')
            ->assertJsonMissingPath('data.latest_version.files.0.disk')
            ->assertJsonMissingPath('data.latest_version.files.0.path')
            ->assertJsonMissingPath('data.latest_version.files.0.checksum')
            ->assertJsonMissingPath('data.latest_version.files.0.metadata')
            ->assertJsonMissingPath('data.latest_version.files.0.uploaded_by_id');
    }

    public function test_landlord_document_response_exposes_workflow_actions(): void
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
            'metadata' => [
                'source' => 'manual',
            ],
        ]);
        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'document_template_id' => $template->id,
            'version_number' => 1,
            'status' => DocumentVersion::STATUS_GENERATED,
            'generated_by_id' => $user->id,
        ]);
        Storage::disk($this->documentsDisk())->put('documents/generated.pdf', '%PDF-1.4 generated');
        DocumentFile::factory()->create([
            'document_version_id' => $version->id,
            'file_type' => DocumentFile::TYPE_GENERATED_PDF,
            'disk' => $this->documentsDisk(),
            'path' => 'documents/generated.pdf',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/documents/'.$document->id)
            ->assertOk()
            ->assertJsonPath('data.document_template_id', $template->id)
            ->assertJsonPath('data.metadata.source', 'manual')
            ->assertJsonPath('data.template.id', $template->id)
            ->assertJsonPath('data.latest_version.document_template_id', $template->id)
            ->assertJsonPath('data.latest_version.generated_by_id', $user->id)
            ->assertJsonPath('data.latest_version.files.0.path', 'documents/generated.pdf')
            ->assertJsonPath('data.actions.generate', true)
            ->assertJsonPath('data.actions.share', true)
            ->assertJsonPath('data.actions.void', true)
            ->assertJsonPath('data.actions.download', true)
            ->assertJsonPath('data.actions.upload_signed', true)
            ->assertJsonPath('data.actions.download_signed', false)
            ->assertJsonPath('data.actions.create_reminder', true);
    }

    public function test_reminder_validates_reminder_before_due_date(): void
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
        $property->forceFill([
            'area_living' => '82.50',
            'rooms' => 3,
            'floor' => 2,
        ])->save();
        $bankAccount = BankAccount::factory()->create([
            'user_id' => $user->id,
            'organization_id' => null,
            'account_holder' => 'Erika Vermieter',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COLSDEDDXXX',
        ]);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
            'tenant_id' => $tenant->id,
            'bank_account_id' => $bankAccount->id,
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
            'content' => '<h1>{{ document.title }}</h1><p>{{ landlord.name }}</p><p>{{ tenant.name }}</p><p>{{ property.area_living }}</p><p>{{ property.rooms }}</p><p>{{ property.floor }}</p><p>{{ rental_agreement.rent_cold }}</p><p>{{ bank_account.iban }}</p>',
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
            ->assertJsonPath('data.latest_version.metadata.renderer', 'dompdf')
            ->assertJsonPath('data.latest_version.data_snapshot.landlord.name', 'Erika Vermieter')
            ->assertJsonPath('data.latest_version.data_snapshot.tenant.name', 'Max Mieter')
            ->assertJsonPath('data.latest_version.data_snapshot.bank_account.account_holder', 'Erika Vermieter')
            ->assertJsonPath('data.latest_version.data_snapshot.bank_account.iban', 'DE89370400440532013000')
            ->assertJsonPath('data.latest_version.data_snapshot.property.area_living', '82.50')
            ->assertJsonPath('data.latest_version.data_snapshot.property.rooms', fn (mixed $value): bool => (string) $value === '3')
            ->assertJsonPath('data.latest_version.data_snapshot.property.floor', fn (mixed $value): bool => (string) $value === '2')
            ->assertJsonPath('data.latest_version.data_snapshot.rental_agreement.rent_cold', '900.00')
            ->assertJsonPath('data.latest_version.content_snapshot', '<h1>Wohnraummietvertrag</h1><p>Erika Vermieter</p><p>Max Mieter</p><p>82.50</p><p>3</p><p>2</p><p>900.00</p><p>DE89370400440532013000</p>')
            ->assertJsonCount(1, 'data.latest_version.files');

        $filePath = $response->json('data.latest_version.files.0.path');

        Storage::disk($this->documentsDisk())->assertExists($filePath);
        $this->assertStringStartsWith('%PDF-', Storage::disk($this->documentsDisk())->get($filePath));

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

        $latestVersion = DocumentVersion::query()
            ->where('document_id', $document->id)
            ->where('version_number', 1)
            ->with('files')
            ->firstOrFail();

        $this->assertSame('dompdf', $latestVersion->metadata['renderer']);
        $this->assertSame('dompdf', $latestVersion->files->firstOrFail()->metadata['renderer']);

        $download = $this->actingAs($user, 'sanctum')
            ->get('/api/documents/'.$document->id.'/download')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $download->streamedContent());
    }

    public function test_landlord_can_generate_multi_page_pdf_from_plain_text_template(): void
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
            'content' => collect(range(1, 140))
                ->map(fn (int $line): string => 'Abschnitt '.$line.' fuer {{ tenant.name }}')
                ->implode("\n"),
        ]);
        $document = Document::factory()->create([
            'documentable_type' => RentalAgreement::class,
            'documentable_id' => $agreement->id,
            'document_template_id' => $template->id,
            'document_type' => 'rental_agreement_contract',
            'status' => Document::STATUS_DRAFT,
            'created_by_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/generate')
            ->assertCreated()
            ->assertJsonPath('data.latest_version.metadata.renderer', 'dompdf');

        $pdfContents = Storage::disk($this->documentsDisk())->get(
            $response->json('data.latest_version.files.0.path')
        );

        $this->assertStringStartsWith('%PDF-', $pdfContents);
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $pdfContents));
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

    public function test_tenant_cannot_access_or_sign_unshared_document_for_own_rental_agreement(): void
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

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/documents/'.$document->id)
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->get('/api/documents/'.$document->id.'/download')
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/documents/'.$document->id.'/reminders')
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/signed-upload', [
                'file' => UploadedFile::fake()->create('signed-contract.pdf', 120, 'application/pdf'),
            ])
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

    public function test_pdf_generation_snapshots_active_organization_document_layout(): void
    {
        Storage::fake($this->documentsDisk());

        $organization = Organization::factory()->create([
            'name' => 'Muster Hausverwaltung',
        ]);
        $user = User::factory()->create([
            'name' => 'Erika Vermieter',
            'organization_id' => $organization->id,
        ]);
        $user->assignRole(RoleName::Landlord->value);
        $property = $this->propertyManagedBy($user);
        $agreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $user->id,
        ]);
        $template = DocumentTemplate::factory()->create([
            'document_type' => 'rental_agreement_contract',
            'locale' => 'de-DE',
            'status' => DocumentTemplate::STATUS_ACTIVE,
            'content' => '<h1>{{ document.title }}</h1>',
        ]);
        $layout = DocumentLayoutTemplate::factory()->active()->create([
            'owner_type' => Organization::class,
            'owner_id' => $organization->id,
            'document_type' => 'rental_agreement_contract',
            'locale' => 'de-DE',
            'version' => 1,
            'name' => 'Mietvertrags-Briefkopf',
            'header_enabled' => true,
            'footer_enabled' => true,
            'page_numbers_enabled' => true,
            'header_content' => '<p>{{ organization.name }} · Version {{ document.version_number }}</p>',
            'footer_content' => '<p>{{ landlord.name }} · {{ document.generated_at }}</p>',
            'placeholders' => [
                'document.generated_at',
                'document.version_number',
                'landlord.name',
                'organization.name',
            ],
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
            ->assertJsonPath('data.latest_version.document_layout_template_id', $layout->id)
            ->assertJsonPath('data.latest_version.layout_snapshot.name', 'Mietvertrags-Briefkopf')
            ->assertJsonPath('data.latest_version.layout_snapshot.owner_type', DocumentLayoutTemplate::OWNER_TYPE_ORGANIZATION)
            ->assertJsonPath('data.latest_version.layout_snapshot.header_enabled', true)
            ->assertJsonPath('data.latest_version.layout_snapshot.footer_enabled', true)
            ->assertJsonPath('data.latest_version.data_snapshot.organization.name', 'Muster Hausverwaltung')
            ->assertJsonPath('data.latest_version.data_snapshot.document.version_number', 1)
            ->assertJsonPath('data.latest_version.data_snapshot.document.generated_at', fn (mixed $value): bool => is_string($value) && $value !== '')
            ->assertJsonPath('data.latest_version.files.0.metadata.document_layout_template_id', $layout->id);

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
            'status' => Document::STATUS_SHARED,
        ]);
        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'status' => DocumentVersion::STATUS_SHARED,
        ]);
        Storage::disk($this->documentsDisk())->put('documents/shared-generated.pdf', '%PDF-1.4 shared');
        DocumentFile::factory()->create([
            'document_version_id' => $version->id,
            'file_type' => DocumentFile::TYPE_GENERATED_PDF,
            'disk' => $this->documentsDisk(),
            'path' => 'documents/shared-generated.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($user, 'sanctum')
            ->get('/api/documents/'.$document->id.'/download')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/documents/'.$document->id.'/signed-upload', [
                'file' => UploadedFile::fake()->create('signed-contract.pdf', 120, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', Document::STATUS_SIGNED_UPLOADED)
            ->assertJsonPath('data.latest_version.status', DocumentVersion::STATUS_SIGNED_UPLOADED)
            ->assertJsonMissingPath('data.latest_version.files.0.path');

        $signedFilePath = DocumentFile::query()
            ->where('document_version_id', $version->id)
            ->where('file_type', DocumentFile::TYPE_SIGNED_UPLOAD)
            ->latest('id')
            ->value('path');

        $this->assertIsString($signedFilePath);
        Storage::disk($this->documentsDisk())->assertExists($signedFilePath);
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
