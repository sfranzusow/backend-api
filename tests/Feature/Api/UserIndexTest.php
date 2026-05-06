<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate(PermissionName::UsersViewAny->value, 'web');

        $adminRole = Role::findOrCreate(RoleName::Admin->value, 'web');
        Role::findOrCreate(RoleName::User->value, 'web');

        $adminRole->givePermissionTo(PermissionName::UsersViewAny->value);
    }

    public function test_returns_401_for_guests_when_listing_users(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_allows_admin_to_list_users(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        User::factory()->count(2)->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/users');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_filters_users_by_phone_number_and_organization(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $organization = Organization::factory()->create([
            'name' => 'Filter Verwaltung',
            'type' => 'property_management',
        ]);

        $matchingUser = User::factory()->create([
            'phone_number' => '+49 30 123456',
            'organization_id' => $organization->id,
        ]);

        User::factory()->create([
            'phone_number' => '+49 30 999999',
            'organization_id' => Organization::factory(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/users?phone_number=123&organization_id='.$organization->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingUser->id)
            ->assertJsonPath('data.0.organization.name', 'Filter Verwaltung');
    }

    public function test_forbids_normal_user_from_listing_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/users');

        $response->assertForbidden();
    }
}
