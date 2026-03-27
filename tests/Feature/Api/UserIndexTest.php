<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate(PermissionName::UsersViewAny->value, 'web');

    $adminRole = Role::findOrCreate(RoleName::Admin->value, 'web');
    Role::findOrCreate(RoleName::User->value, 'web');

    $adminRole->givePermissionTo(PermissionName::UsersViewAny->value);
});

it('returns 401 for guests when listing users', function () {
    /** @var \Tests\TestCase $this */ 
    $this->getJson('/api/users')
        ->assertUnauthorized();
});

it('allows admin to list users', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    User::factory()->count(2)->create();   
    /** @var \Tests\TestCase $this */  
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/users')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('forbids normal user from listing users', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::User->value);
    /** @var \Tests\TestCase $this */ 
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/users')
        ->assertForbidden();
});