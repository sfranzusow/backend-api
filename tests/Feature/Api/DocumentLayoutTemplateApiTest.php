<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\DocumentLayoutTemplate;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentLayoutTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_cannot_access_document_layout_templates(): void
    {
        $layout = DocumentLayoutTemplate::factory()->create();

        $this->getJson('/api/document-layout-templates')->assertUnauthorized();
        $this->postJson('/api/document-layout-templates')->assertUnauthorized();
        $this->getJson('/api/document-layout-templates/'.$layout->id)->assertUnauthorized();
        $this->patchJson('/api/document-layout-templates/'.$layout->id)->assertUnauthorized();
        $this->postJson('/api/document-layout-templates/'.$layout->id.'/activate')->assertUnauthorized();
        $this->deleteJson('/api/document-layout-templates/'.$layout->id)->assertUnauthorized();
    }

    public function test_admin_can_create_active_organization_layout_and_archive_previous_active_layout(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $organization = Organization::factory()->create();
        $previousLayout = DocumentLayoutTemplate::factory()->active()->create([
            'owner_type' => Organization::class,
            'owner_id' => $organization->id,
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            'locale' => 'de-DE',
            'version' => 1,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/document-layout-templates', [
                'owner_type' => DocumentLayoutTemplate::OWNER_TYPE_ORGANIZATION,
                'owner_id' => $organization->id,
                'name' => 'Briefkopf Mietvertrag',
                'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
                'locale' => 'de-DE',
                'version' => 2,
                'status' => DocumentLayoutTemplate::STATUS_ACTIVE,
                'header_enabled' => true,
                'footer_enabled' => true,
                'page_numbers_enabled' => true,
                'header_content' => '<p>{{ organization.name }}</p>',
                'footer_content' => '<p>{{ document.title }} · {{ document.version_number }}</p>',
                'metadata' => [
                    'accent_color' => '#111827',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.owner_type', DocumentLayoutTemplate::OWNER_TYPE_ORGANIZATION)
            ->assertJsonPath('data.owner_id', $organization->id)
            ->assertJsonPath('data.status', DocumentLayoutTemplate::STATUS_ACTIVE)
            ->assertJsonPath('data.header_enabled', true)
            ->assertJsonPath('data.footer_enabled', true)
            ->assertJsonPath('data.page_numbers_enabled', true)
            ->assertJsonPath('data.placeholders', [
                'document.title',
                'document.version_number',
                'organization.name',
            ])
            ->assertJsonPath('data.metadata.accent_color', '#111827');

        $this->assertDatabaseHas('document_layout_templates', [
            'id' => $previousLayout->id,
            'status' => DocumentLayoutTemplate::STATUS_ARCHIVED,
        ]);
        $this->assertDatabaseHas('document_layout_templates', [
            'id' => $response->json('data.id'),
            'owner_type' => Organization::class,
            'owner_id' => $organization->id,
            'status' => DocumentLayoutTemplate::STATUS_ACTIVE,
            'created_by_id' => $admin->id,
        ]);
    }

    public function test_landlord_defaults_layout_owner_to_own_organization(): void
    {
        $organization = Organization::factory()->create();
        $landlord = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $landlord->assignRole(RoleName::Landlord->value);

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/document-layout-templates', [
                'name' => 'Eigener Briefkopf',
                'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
                'header_enabled' => true,
                'header_content' => '<p>{{ landlord.name }}</p>',
            ])
            ->assertCreated()
            ->assertJsonPath('data.owner_type', DocumentLayoutTemplate::OWNER_TYPE_ORGANIZATION)
            ->assertJsonPath('data.owner_id', $organization->id)
            ->assertJsonPath('data.created_by_id', $landlord->id)
            ->assertJsonPath('data.placeholders', ['landlord.name']);
    }

    public function test_landlord_cannot_manage_layout_for_another_owner(): void
    {
        $landlord = User::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);
        $landlord->assignRole(RoleName::Landlord->value);
        $otherOrganization = Organization::factory()->create();

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/document-layout-templates', [
                'owner_type' => DocumentLayoutTemplate::OWNER_TYPE_ORGANIZATION,
                'owner_id' => $otherOrganization->id,
                'name' => 'Fremder Briefkopf',
                'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['owner_id']);
    }

    public function test_landlord_can_update_activate_and_delete_own_inactive_layout(): void
    {
        $landlord = User::factory()->create();
        $landlord->assignRole(RoleName::Landlord->value);
        $layout = DocumentLayoutTemplate::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $landlord->id,
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
            'status' => DocumentLayoutTemplate::STATUS_DRAFT,
            'version' => 1,
            'header_enabled' => false,
            'header_content' => null,
            'placeholders' => [],
        ]);

        $this->actingAs($landlord, 'sanctum')
            ->patchJson('/api/document-layout-templates/'.$layout->id, [
                'footer_enabled' => true,
                'footer_content' => '<p>{{ document.generated_at }}</p>',
            ])
            ->assertOk()
            ->assertJsonPath('data.footer_enabled', true)
            ->assertJsonPath('data.placeholders', ['document.generated_at']);

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/document-layout-templates/'.$layout->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.status', DocumentLayoutTemplate::STATUS_ACTIVE);

        $this->actingAs($landlord, 'sanctum')
            ->deleteJson('/api/document-layout-templates/'.$layout->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($landlord, 'sanctum')
            ->patchJson('/api/document-layout-templates/'.$layout->id, [
                'status' => DocumentLayoutTemplate::STATUS_ARCHIVED,
            ])
            ->assertOk();

        $this->actingAs($landlord, 'sanctum')
            ->deleteJson('/api/document-layout-templates/'.$layout->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('document_layout_templates', [
            'id' => $layout->id,
        ]);
    }
}
