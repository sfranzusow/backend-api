<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('returns the authenticated profile at GET /api/user', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::User->value);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/user')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

it('allows admin to create a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/users', [
            'name' => 'Neu Nutzer',
            'email' => 'neu@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'neu@example.com');

    expect(User::query()->where('email', 'neu@example.com')->exists())->toBeTrue();
});

it('forbids non-admin from creating users', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::User->value);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/users', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])
        ->assertForbidden();
});

it('allows admin to show another user', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $other = User::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/users/'.$other->id)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $other->id);
});

it('allows admin to update a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $target = User::factory()->create(['name' => 'Alt']);

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/users/'.$target->id, [
            'name' => 'Neu',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Neu');

    expect($target->fresh()->name)->toBe('Neu');
});

it('allows user to update own profile', function () {
    $user = User::factory()->create(['name' => 'Ich']);
    $user->assignRole(RoleName::User->value);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/users/'.$user->id, [
            'name' => 'Ich Neu',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Ich Neu');
});

it('prevents deleting own account', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/users/'.$admin->id)
        ->assertForbidden();
});

it('allows admin to delete another user', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $other = User::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/users/'.$other->id)
        ->assertNoContent();

    expect(User::query()->find($other->id))->toBeNull();
});
