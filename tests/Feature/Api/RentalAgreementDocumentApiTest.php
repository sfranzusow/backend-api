<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Property;
use App\Models\RentalAgreement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->getJson('/api/rental-agreements/'.$agreement->id.'/documents')->assertUnauthorized();
        $this->postJson('/api/rental-agreements/'.$agreement->id.'/documents')->assertUnauthorized();
        $this->getJson('/api/documents/'.$document->id)->assertUnauthorized();
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

    private function propertyManagedBy(User $landlord): Property
    {
        $property = Property::factory()->create();
        $property->users()->attach($landlord->id, ['role' => RoleName::Landlord->value]);

        return $property;
    }
}
