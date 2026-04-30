<?php

namespace Tests\Feature\Api;

use App\Models\Address;
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

    public function test_authenticated_user_can_filter_addresses_by_city_and_country(): void
    {
        $user = User::factory()->create();

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

    public function test_authenticated_user_can_create_and_update_address(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user, 'sanctum')->postJson('/api/addresses', [
            'street' => 'Musterstrasse',
            'house_number' => '10',
            'zip_code' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
        ]);

        $create->assertCreated()->assertJsonPath('data.city', 'Berlin');

        $id = $create->json('data.id');

        $this->actingAs($user, 'sanctum')->patchJson('/api/addresses/'.$id, [
            'city' => 'Hamburg',
        ])->assertSuccessful()->assertJsonPath('data.city', 'Hamburg');
    }

    public function test_authenticated_user_can_update_address_via_put(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create([
            'city' => 'Berlin',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/addresses/'.$address->id, [
                'city' => 'Hamburg',
                'country' => 'DE',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.city', 'Hamburg')
            ->assertJsonPath('data.country', 'DE');
    }

    public function test_authenticated_user_can_delete_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/addresses/'.$address->id)
            ->assertNoContent();

        $this->assertNull(Address::query()->find($address->id));
    }

    public function test_authenticated_user_can_show_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/addresses/'.$address->id)
            ->assertSuccessful()
            ->assertJsonPath('data.id', $address->id);
    }
}
