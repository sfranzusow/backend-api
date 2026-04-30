<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Address;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_cannot_access_addresses(): void
    {
        $this->getJson('/api/addresses')->assertUnauthorized();
    }

    public function test_admin_can_filter_addresses_by_city_and_country(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        Address::factory()->create([
            'city' => 'Berlin',
            'country' => 'DE',
        ]);

        Address::factory()->create([
            'city' => 'Vienna',
            'country' => 'AT',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/addresses?city=ber&country=de')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.city', 'Berlin');
    }

    public function test_landlord_can_create_and_update_owned_address(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);

        $create = $this->actingAs($user, 'sanctum')->postJson('/api/addresses', [
            'street' => 'Musterstrasse',
            'house_number' => '10',
            'zip_code' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
        ]);

        $create->assertCreated()->assertJsonPath('data.city', 'Berlin');

        $id = $create->json('data.id');
        Property::factory()->create(['address_id' => $id])
            ->users()
            ->attach($user->id, ['role' => RoleName::Landlord->value]);

        $this->actingAs($user, 'sanctum')->patchJson('/api/addresses/'.$id, [
            'city' => 'Hamburg',
        ])->assertSuccessful()->assertJsonPath('data.city', 'Hamburg');
    }

    public function test_landlord_can_update_address_via_put(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $address = Address::factory()->create([
            'city' => 'Berlin',
        ]);
        Property::factory()->create(['address_id' => $address->id])
            ->users()
            ->attach($user->id, ['role' => RoleName::Landlord->value]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/addresses/'.$address->id, [
                'city' => 'Hamburg',
                'country' => 'DE',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.city', 'Hamburg')
            ->assertJsonPath('data.country', 'DE');
    }

    public function test_landlord_can_delete_owned_address(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $address = Address::factory()->create();
        Property::factory()->create(['address_id' => $address->id])
            ->users()
            ->attach($user->id, ['role' => RoleName::Landlord->value]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/addresses/'.$address->id)
            ->assertNoContent();

        $this->assertNull(Address::query()->find($address->id));
    }

    public function test_landlord_can_show_owned_address(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);
        $address = Address::factory()->create();
        Property::factory()->create(['address_id' => $address->id])
            ->users()
            ->attach($user->id, ['role' => RoleName::Landlord->value]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/addresses/'.$address->id)
            ->assertSuccessful()
            ->assertJsonPath('data.id', $address->id);
    }

    public function test_tenant_cannot_access_addresses(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Tenant->value);
        $address = Address::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/addresses')->assertForbidden();
        $this->actingAs($user, 'sanctum')->getJson('/api/addresses/'.$address->id)->assertForbidden();
    }

    public function test_user_cannot_create_address(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        $this->actingAs($user, 'sanctum')->postJson('/api/addresses', [
            'street' => 'Musterstrasse',
            'house_number' => '10',
            'zip_code' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
        ])->assertForbidden();
    }

    public function test_landlord_only_sees_owned_addresses_in_index(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Landlord->value);

        $ownedAddress = Address::factory()->create();
        Property::factory()->create(['address_id' => $ownedAddress->id])
            ->users()
            ->attach($user->id, ['role' => RoleName::Landlord->value]);

        Address::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/addresses')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownedAddress->id);
    }
}
