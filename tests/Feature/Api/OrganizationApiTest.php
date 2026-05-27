<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\BankAccount;
use App\Models\DocumentLayoutTemplate;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_cannot_access_organizations(): void
    {
        $organization = Organization::factory()->create();

        $this->getJson('/api/organizations')->assertUnauthorized();
        $this->postJson('/api/organizations')->assertUnauthorized();
        $this->getJson('/api/organizations/'.$organization->id)->assertUnauthorized();
        $this->patchJson('/api/organizations/'.$organization->id)->assertUnauthorized();
        $this->deleteJson('/api/organizations/'.$organization->id)->assertUnauthorized();
    }

    public function test_admin_can_crud_organizations(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/organizations', [
                'name' => 'Muster Verwaltung',
                'type' => 'property_management',
                'email' => 'info@muster.test',
                'phone_number' => '+49 30 123456',
                'website' => 'https://muster.test',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Muster Verwaltung')
            ->assertJsonPath('data.type', 'property_management')
            ->assertJsonPath('data.email', 'info@muster.test');

        $organizationId = $response->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/organizations?name=Muster')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $organizationId);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/organizations/'.$organizationId)
            ->assertOk()
            ->assertJsonPath('data.id', $organizationId);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/organizations/'.$organizationId, [
                'name' => 'Muster Verwaltung GmbH',
                'email' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Muster Verwaltung GmbH')
            ->assertJsonPath('data.email', null);

        $this->assertDatabaseHas('organizations', [
            'id' => $organizationId,
            'name' => 'Muster Verwaltung GmbH',
            'email' => null,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/organizations/'.$organizationId)
            ->assertNoContent();

        $this->assertDatabaseMissing('organizations', [
            'id' => $organizationId,
        ]);
    }

    public function test_non_admin_cannot_manage_organizations(): void
    {
        $landlord = User::factory()->create();
        $landlord->assignRole(RoleName::Landlord->value);
        $organization = Organization::factory()->create();

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/organizations')
            ->assertForbidden();

        $this->actingAs($landlord, 'sanctum')
            ->postJson('/api/organizations', [
                'name' => 'Nicht erlaubt',
            ])
            ->assertForbidden();

        $this->actingAs($landlord, 'sanctum')
            ->getJson('/api/organizations/'.$organization->id)
            ->assertForbidden();

        $this->actingAs($landlord, 'sanctum')
            ->patchJson('/api/organizations/'.$organization->id, [
                'name' => 'Nicht erlaubt',
            ])
            ->assertForbidden();

        $this->actingAs($landlord, 'sanctum')
            ->deleteJson('/api/organizations/'.$organization->id)
            ->assertForbidden();
    }

    public function test_organization_name_must_be_unique(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Organization::factory()->create([
            'name' => 'Einmalig GmbH',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/organizations', [
                'name' => 'Einmalig GmbH',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_cannot_delete_organization_with_dependencies(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $organization = Organization::factory()->create();
        User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/organizations/'.$organization->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization']);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
        ]);
    }

    public function test_admin_cannot_delete_organization_with_bank_accounts_or_layouts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $bankAccountOrganization = Organization::factory()->create();
        $layoutOrganization = Organization::factory()->create();

        BankAccount::factory()->create([
            'organization_id' => $bankAccountOrganization->id,
            'user_id' => null,
        ]);
        DocumentLayoutTemplate::factory()->create([
            'owner_type' => Organization::class,
            'owner_id' => $layoutOrganization->id,
            'document_type' => DocumentTemplate::TYPE_RENTAL_AGREEMENT_CONTRACT,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/organizations/'.$bankAccountOrganization->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/organizations/'.$layoutOrganization->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization']);
    }
}
