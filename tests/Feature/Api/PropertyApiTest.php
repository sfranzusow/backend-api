<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Address;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_authenticated_user_can_create_property(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $address = Address::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/properties', [
            'address_id' => $address->id,
            'type' => 'apartment',
            'area_living' => 80.5,
            'rooms' => 3,
            'status' => 'available',
        ])->assertCreated()->assertJsonPath('data.address_id', $address->id);
    }

    public function test_guest_cannot_access_property_endpoints(): void
    {
        $property = Property::factory()->create();

        $this->getJson('/api/properties')->assertUnauthorized();
        $this->getJson('/api/properties/'.$property->id)->assertUnauthorized();
    }

    public function test_landlord_can_list_properties_with_filters(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);

        $matchingAddress = Address::factory()->create();
        $matchingProperty = Property::factory()->create([
            'address_id' => $matchingAddress->id,
            'type' => 'apartment',
            'status' => 'available',
        ]);
        $matchingProperty->users()->attach($user->id, ['role' => RoleName::Landlord->value]);

        Property::factory()->create([
            'type' => 'office',
            'status' => 'sold',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/properties?status=available&type=apartment&address_id='.$matchingAddress->id)
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingProperty->id);
    }

    public function test_forbids_user_without_property_role_from_listing_properties(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/properties')
            ->assertForbidden();
    }

    public function test_forbids_non_landlord_from_creating_property(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);
        $address = Address::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/properties', [
            'address_id' => $address->id,
            'type' => 'apartment',
            'area_living' => 80.5,
            'rooms' => 3,
            'status' => 'available',
        ])->assertForbidden();
    }

    public function test_landlord_of_property_can_view_property(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = Property::factory()->create();
        $property->users()->attach($user->id, ['role' => RoleName::Landlord->value]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertSuccessful()
            ->assertJsonPath('data.id', $property->id);
    }

    public function test_forbids_showing_property_for_non_member(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $property = Property::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertForbidden();
    }

    public function test_landlord_of_property_can_update_property_via_put(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = Property::factory()->create([
            'status' => 'available',
        ]);
        $property->users()->attach($user->id, ['role' => RoleName::Landlord->value]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/properties/'.$property->id, [
                'status' => 'rented',
                'rooms' => 4,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'rented')
            ->assertJsonPath('data.rooms', 4);
    }

    public function test_landlord_of_property_can_sync_property_members(): void
    {
        $authUser = User::factory()->create();
        $authUser->assignRole(RoleName::Landlord->value);
        $property = Property::factory()->create();
        $member = User::factory()->create();
        $property->users()->attach($authUser->id, ['role' => RoleName::Landlord->value]);

        $this->actingAs($authUser, 'sanctum')
            ->putJson('/api/properties/'.$property->id.'/members', [
                'members' => [
                    [
                        'user_id' => $member->id,
                        'role' => 'tenant',
                        'start_date' => '2026-01-01',
                    ],
                ],
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.members.0.role', 'tenant');

        $this->assertTrue($property->fresh()->users()->whereKey($member->id)->exists());
    }

    public function test_forbids_non_landlord_from_syncing_property_members(): void
    {
        $authUser = User::factory()->create();
        $authUser->assignRole(RoleName::Tenant->value);
        $property = Property::factory()->create();
        $property->users()->attach($authUser->id, ['role' => RoleName::Tenant->value]);
        $member = User::factory()->create();

        $this->actingAs($authUser, 'sanctum')
            ->putJson('/api/properties/'.$property->id.'/members', [
                'members' => [
                    [
                        'user_id' => $member->id,
                        'role' => 'tenant',
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_it_validates_sync_property_members_dates(): void
    {
        $authUser = User::factory()->create();
        $authUser->assignRole(RoleName::Landlord->value);
        $property = Property::factory()->create();
        $property->users()->attach($authUser->id, ['role' => RoleName::Landlord->value]);
        $member = User::factory()->create();

        $this->actingAs($authUser, 'sanctum')
            ->putJson('/api/properties/'.$property->id.'/members', [
                'members' => [
                    [
                        'user_id' => $member->id,
                        'role' => 'tenant',
                        'start_date' => '2026-02-01',
                        'end_date' => '2026-01-01',
                    ],
                ],
            ])
            ->assertUnprocessable();
    }

    public function test_landlord_of_property_can_delete_property(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $property = Property::factory()->create();
        $property->users()->attach($user->id, ['role' => RoleName::Landlord->value]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/properties/'.$property->id)
            ->assertNoContent();

        $this->assertNull(Property::query()->find($property->id));
    }

    public function test_forbids_deleting_property_for_non_landlord_member(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $property = Property::factory()->create();
        $property->users()->attach($user->id, ['role' => RoleName::Tenant->value]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/properties/'.$property->id)
            ->assertForbidden();
    }
}
