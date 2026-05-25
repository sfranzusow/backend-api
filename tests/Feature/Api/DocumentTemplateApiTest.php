<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\DocumentTemplate;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_cannot_access_document_template_endpoints(): void
    {
        $template = DocumentTemplate::factory()->create([
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            'status' => DocumentTemplate::STATUS_ACTIVE,
        ]);

        $this->getJson('/api/document-templates')->assertUnauthorized();
        $this->getJson('/api/document-template-placeholders?document_type='.DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT)
            ->assertUnauthorized();
        $this->postJson('/api/document-templates', [])->assertUnauthorized();
        $this->getJson('/api/document-templates/'.$template->id)->assertUnauthorized();
        $this->patchJson('/api/document-templates/'.$template->id, [])->assertUnauthorized();
        $this->postJson('/api/document-templates/'.$template->id.'/activate')->assertUnauthorized();
        $this->deleteJson('/api/document-templates/'.$template->id)->assertUnauthorized();
    }

    public function test_landlord_can_list_and_show_only_active_document_templates(): void
    {
        $landlord = $this->userWithRole(RoleName::Landlord);
        $activeTemplate = DocumentTemplate::factory()->create([
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            'status' => DocumentTemplate::STATUS_ACTIVE,
        ]);
        $draftTemplate = DocumentTemplate::factory()->create([
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            'version' => 2,
            'status' => DocumentTemplate::STATUS_DRAFT,
        ]);

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/document-templates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeTemplate->id);

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/document-templates/'.$activeTemplate->id)
            ->assertOk()
            ->assertJsonPath('data.id', $activeTemplate->id);

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/document-templates/'.$draftTemplate->id)
            ->assertForbidden();
    }

    public function test_landlord_can_list_placeholder_metadata_for_document_type(): void
    {
        $landlord = $this->userWithRole(RoleName::Landlord);

        $response = $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/document-template-placeholders?document_type='.DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT)
            ->assertOk()
            ->assertJsonFragment([
                'path' => 'rental_agreement.notes',
                'label' => 'Individuelle Vereinbarungen',
                'group' => 'Mietvertrag',
                'type' => 'string',
                'nullable' => true,
                'example' => '{{ rental_agreement.notes }}',
            ])
            ->assertJsonFragment([
                'path' => 'tenant.name',
                'label' => 'Name des Mieters',
                'group' => 'Mieter',
                'type' => 'string',
                'nullable' => false,
                'example' => '{{ tenant.name }}',
            ]);

        $this->assertCount(
            count(DocumentTemplate::allowedPlaceholdersFor(DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT)),
            $response->json('data')
        );
    }

    public function test_placeholder_metadata_requires_known_document_type(): void
    {
        $landlord = $this->userWithRole(RoleName::Landlord);

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/document-template-placeholders')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document_type');

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/document-template-placeholders?document_type=unknown_type')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document_type');
    }

    public function test_admin_can_create_draft_template_and_extract_placeholders_from_content(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/document-templates', [
                'name' => 'Wohnraummietvertrag flexibel',
                'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
                'template_type' => 'residential',
                'locale' => 'de-DE',
                'version' => 2,
                'content' => '<p>{{ tenant.name }} mietet {{ property.address }}</p>',
                'metadata' => [
                    'legal_notice' => 'juristisch zu pruefen',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Wohnraummietvertrag flexibel')
            ->assertJsonPath('data.status', DocumentTemplate::STATUS_DRAFT)
            ->assertJsonPath('data.created_by_id', $admin->id)
            ->assertJsonPath('data.placeholders.0', 'property.address')
            ->assertJsonPath('data.placeholders.1', 'tenant.name')
            ->assertJsonPath('data.metadata.legal_notice', 'juristisch zu pruefen');

        $this->assertDatabaseHas('document_templates', [
            'name' => 'Wohnraummietvertrag flexibel',
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            'template_type' => 'residential',
            'locale' => 'de-DE',
            'version' => 2,
            'status' => DocumentTemplate::STATUS_DRAFT,
            'created_by_id' => $admin->id,
        ]);
    }

    public function test_unknown_placeholders_are_rejected_before_storing_templates(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/document-templates', [
                'name' => 'Ungueltige Vorlage',
                'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
                'content' => '<p>{{ tenant.name }} {{ made.up.value }}</p>',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('placeholders');
    }

    public function test_bank_account_placeholders_are_allowed_for_rental_agreement_templates(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/document-templates', [
                'name' => 'Mietvertrag mit Zahlungsempfaenger',
                'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
                'version' => 3,
                'content' => '<p>{{ bank_account.account_holder }} {{ bank_account.iban }} {{ bank_account.bic }}</p>',
            ])
            ->assertCreated()
            ->assertJsonPath('data.placeholders.0', 'bank_account.account_holder')
            ->assertJsonPath('data.placeholders.1', 'bank_account.bic')
            ->assertJsonPath('data.placeholders.2', 'bank_account.iban');
    }

    public function test_property_detail_placeholders_are_allowed_for_rental_agreement_templates(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/document-templates', [
                'name' => 'Mietvertrag mit Objektdetails',
                'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
                'version' => 4,
                'content' => '<p>{{ property.area_living }} {{ property.rooms }} {{ property.floor }}</p>',
            ])
            ->assertCreated()
            ->assertJsonPath('data.placeholders.0', 'property.area_living')
            ->assertJsonPath('data.placeholders.1', 'property.floor')
            ->assertJsonPath('data.placeholders.2', 'property.rooms');
    }

    public function test_activation_archives_older_active_templates_for_the_same_lookup(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $oldTemplate = DocumentTemplate::factory()->create([
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            'template_type' => 'residential',
            'locale' => 'de-DE',
            'version' => 1,
            'status' => DocumentTemplate::STATUS_ACTIVE,
        ]);
        $newTemplate = DocumentTemplate::factory()->create([
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            'template_type' => 'residential',
            'locale' => 'de-DE',
            'version' => 2,
            'status' => DocumentTemplate::STATUS_DRAFT,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/document-templates/'.$newTemplate->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.id', $newTemplate->id)
            ->assertJsonPath('data.status', DocumentTemplate::STATUS_ACTIVE);

        $this->assertDatabaseHas('document_templates', [
            'id' => $oldTemplate->id,
            'status' => DocumentTemplate::STATUS_ARCHIVED,
        ]);
        $this->assertDatabaseHas('document_templates', [
            'id' => $newTemplate->id,
            'status' => DocumentTemplate::STATUS_ACTIVE,
        ]);
    }

    public function test_admin_can_update_and_delete_non_active_templates(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $template = DocumentTemplate::factory()->create([
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            'status' => DocumentTemplate::STATUS_DRAFT,
            'content' => '<p>{{ tenant.name }}</p>',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/document-templates/'.$template->id, [
                'name' => 'Neue Bezeichnung',
                'content' => '<p>{{ tenant.name }} {{ landlord.name }}</p>',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Neue Bezeichnung')
            ->assertJsonPath('data.placeholders.0', 'landlord.name')
            ->assertJsonPath('data.placeholders.1', 'tenant.name');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/document-templates/'.$template->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('document_templates', [
            'id' => $template->id,
        ]);
    }

    public function test_active_templates_cannot_be_deleted_directly(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $template = DocumentTemplate::factory()->create([
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            'status' => DocumentTemplate::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/document-templates/'.$template->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_tenants_cannot_list_templates_and_landlords_cannot_manage_them(): void
    {
        $tenant = $this->userWithRole(RoleName::Tenant);
        $landlord = $this->userWithRole(RoleName::Landlord);
        $template = DocumentTemplate::factory()->create([
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            'status' => DocumentTemplate::STATUS_ACTIVE,
        ]);

        $this->actingAs($tenant, 'sanctum')
            ->getJson('/api/document-templates')
            ->assertForbidden();

        $this->actingAs($tenant, 'sanctum')
            ->getJson('/api/document-template-placeholders?document_type='.DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT)
            ->assertForbidden();

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/document-templates', [
                'name' => 'Nicht erlaubt',
                'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            ])
            ->assertForbidden();

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/document-templates/'.$template->id.'/activate')
            ->assertForbidden();
    }

    private function userWithRole(RoleName $roleName): User
    {
        $user = User::factory()->create();
        $user->assignRole($roleName->value);

        return $user;
    }
}
