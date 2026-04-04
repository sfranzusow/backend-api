<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionName;
use App\Enums\RoleName;
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

    public function test_forbids_normal_user_from_listing_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/users');

        $response->assertForbidden();
    }
}
