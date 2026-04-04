<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_returns_the_authenticated_profile_at_get_api_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user');

        $response->assertSuccessful()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_allows_admin_to_create_a_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/users', [
            'name' => 'Neu Nutzer',
            'email' => 'neu@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'neu@example.com');

        $this->assertTrue(User::query()->where('email', 'neu@example.com')->exists());
    }

    public function test_forbids_non_admin_from_creating_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/users', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $response->assertForbidden();
    }

    public function test_allows_admin_to_show_another_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $other = User::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/users/'.$other->id);

        $response->assertSuccessful()
            ->assertJsonPath('data.id', $other->id);
    }

    public function test_allows_admin_to_update_a_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $target = User::factory()->create(['name' => 'Alt']);

        $response = $this->actingAs($admin, 'sanctum')->putJson('/api/users/'.$target->id, [
            'name' => 'Neu',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.name', 'Neu');

        $this->assertSame('Neu', $target->fresh()->name);
    }

    public function test_allows_admin_to_change_another_users_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $target = User::factory()->create();
        $target->assignRole(RoleName::User->value);

        $response = $this->actingAs($admin, 'sanctum')->patchJson('/api/users/'.$target->id, [
            'roles' => [RoleName::Tenant->value],
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.roles', [RoleName::Tenant->value]);

        $this->assertTrue($target->fresh()->hasRole(RoleName::Tenant->value));
        $this->assertFalse($target->fresh()->hasRole(RoleName::User->value));
    }

    public function test_forbids_changing_own_roles_via_api(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $response = $this->actingAs($admin, 'sanctum')->patchJson('/api/users/'.$admin->id, [
            'roles' => [RoleName::User->value],
        ]);

        $response->assertForbidden();
    }

    public function test_allows_user_to_update_own_profile(): void
    {
        $user = User::factory()->create(['name' => 'Ich']);
        $user->assignRole(RoleName::User->value);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/users/'.$user->id, [
            'name' => 'Ich Neu',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.name', 'Ich Neu');
    }

    public function test_allows_user_to_change_own_password_with_current_password(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/users/'.$user->id, [
            'password' => 'newpass11',
            'password_confirmation' => 'newpass11',
            'current_password' => 'password',
        ]);

        $response->assertSuccessful();

        $this->assertTrue(Hash::check('newpass11', $user->fresh()->password));
    }

    public function test_rejects_own_password_change_without_current_password(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/users/'.$user->id, [
            'password' => 'newpass11',
            'password_confirmation' => 'newpass11',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_allows_admin_to_set_another_users_password_without_current_password(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $target = User::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->putJson('/api/users/'.$target->id, [
            'password' => 'adminset12',
            'password_confirmation' => 'adminset12',
        ]);

        $response->assertSuccessful();

        $this->assertTrue(Hash::check('adminset12', $target->fresh()->password));
    }

    public function test_prevents_deleting_own_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson('/api/users/'.$admin->id);

        $response->assertForbidden();
    }

    public function test_allows_admin_to_delete_another_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $other = User::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson('/api/users/'.$other->id);

        $response->assertNoContent();

        $this->assertNull(User::query()->find($other->id));
    }
}
