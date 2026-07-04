<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_user_with_a_role(): void
    {
        $env = $this->seedErp();
        $this->actingAsRole('Administrador', $env['company'], $env['branch']);

        $this->postJson('/api/v1/users', [
            'name' => 'Nuevo', 'email' => 'nuevo@test.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'role' => 'Ventas',
        ])->assertCreated()->assertJsonPath('data.roles.0', 'Ventas');

        $this->assertDatabaseHas('users', ['email' => 'nuevo@test.com']);
    }

    public function test_seller_cannot_manage_users(): void
    {
        $env = $this->seedErp();
        $this->actingAsRole('Ventas', $env['company'], $env['branch']);

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_cannot_delete_last_super_admin(): void
    {
        $env = $this->seedErp();
        $admin = $this->actingAsRole('Super Administrador', $env['company'], $env['branch']);

        $this->deleteJson("/api/v1/users/{$admin->id}")->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
    }

    public function test_admin_can_create_a_custom_role_with_permissions(): void
    {
        $env = $this->seedErp();
        $this->actingAsRole('Administrador', $env['company'], $env['branch']);

        $this->postJson('/api/v1/roles', [
            'name' => 'Cajero Junior',
            'permissions' => ['dashboard.view', 'sales.create'],
        ])->assertCreated()->assertJsonPath('data.permissions_count', 2);

        $role = Role::where('name', 'Cajero Junior')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('sales.create', 'web'));
    }

    public function test_super_administrator_role_cannot_be_edited(): void
    {
        $env = $this->seedErp();
        $this->actingAsRole('Administrador', $env['company'], $env['branch']);
        $role = Role::where('name', 'Super Administrador')->firstOrFail();

        $this->putJson("/api/v1/roles/{$role->id}", ['name' => 'Hacked', 'permissions' => []])
            ->assertStatus(422);
    }

    public function test_permissions_are_grouped_by_module(): void
    {
        $env = $this->seedErp();
        $this->actingAsRole('Administrador', $env['company'], $env['branch']);

        $response = $this->getJson('/api/v1/roles/permissions')->assertOk();
        $modules = collect($response->json('data'))->pluck('module');

        $this->assertTrue($modules->contains('products'));
        $this->assertTrue($modules->contains('sales'));
    }
}
