<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Property;
use App\Models\RentalAgreement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalAgreementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_authenticated_user_can_create_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = Property::factory()->create();
        $landlord = $user;
        $tenant = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/rental-agreements', [
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'date_from' => '2026-01-01',
            'rent_cold' => 900,
            'status' => 'draft',
        ])->assertCreated()->assertJsonPath('data.property_id', $property->id);
    }

    public function test_guest_cannot_access_rental_agreements(): void
    {
        $agreement = RentalAgreement::factory()->create();

        $this->getJson('/api/rental-agreements')->assertUnauthorized();
        $this->getJson('/api/rental-agreements/'.$agreement->id)->assertUnauthorized();
    }

    public function test_landlord_can_filter_own_rental_agreements(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = Property::factory()->create();
        $landlord = $user;
        $tenant = User::factory()->create();

        $matchingAgreement = RentalAgreement::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);

        RentalAgreement::factory()->create([
            'status' => 'draft',
            'landlord_id' => User::factory(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements?status=active&property_id='.$property->id.'&landlord_id='.$landlord->id.'&tenant_id='.$tenant->id)
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingAgreement->id);
    }

    public function test_it_validates_different_landlord_and_tenant(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = Property::factory()->create();
        $sameUser = $user;

        $this->actingAs($user, 'sanctum')->postJson('/api/rental-agreements', [
            'property_id' => $property->id,
            'landlord_id' => $sameUser->id,
            'tenant_id' => $sameUser->id,
            'date_from' => '2026-01-01',
            'rent_cold' => 800,
        ])->assertUnprocessable()->assertJsonValidationErrors(['landlord_id']);
    }

    public function test_tenant_can_show_own_rental_agreement_without_address_data(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements/'.$agreement->id)
            ->assertSuccessful()
            ->assertJsonPath('data.id', $agreement->id)
            ->assertJsonMissingPath('data.property.address');
    }

    public function test_landlord_can_update_own_rental_agreement_via_put(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $agreement = RentalAgreement::factory()->create([
            'landlord_id' => $user->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/rental-agreements/'.$agreement->id, [
                'status' => 'active',
                'notes' => 'Signed',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.notes', 'Signed');
    }

    public function test_landlord_can_delete_own_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $agreement = RentalAgreement::factory()->create([
            'landlord_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/rental-agreements/'.$agreement->id)
            ->assertNoContent();

        $this->assertNull(RentalAgreement::query()->find($agreement->id));
    }

    public function test_user_role_cannot_access_rental_agreement_index(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements')
            ->assertForbidden();
    }

    public function test_tenant_only_sees_own_rental_agreements_in_index(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);

        $ownedAgreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);

        RentalAgreement::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rental-agreements')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownedAgreement->id);
    }

    public function test_tenant_cannot_update_rental_agreement(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $agreement = RentalAgreement::factory()->create([
            'tenant_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/rental-agreements/'.$agreement->id, [
                'notes' => 'Attempt',
            ])
            ->assertForbidden();
    }
}
