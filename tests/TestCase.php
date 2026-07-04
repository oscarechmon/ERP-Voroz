<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;
use Modules\Settings\Models\Branch;
use Modules\Settings\Models\Company;
use Modules\Settings\Models\Warehouse;
use Modules\Users\Database\Seeders\RolePermissionSeeder;

abstract class TestCase extends BaseTestCase
{
    /** Siembra permisos/roles y crea empresa + sucursal + almacén de prueba. */
    protected function seedErp(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::create([
            'business_name' => 'Test SAC', 'ruc' => '20999999999', 'currency' => 'PEN',
            'currency_symbol' => 'S/', 'igv_percent' => 18, 'prices_include_igv' => true, 'is_active' => true,
        ]);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Principal', 'is_main' => true, 'is_active' => true]);
        $warehouse = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Central', 'is_default' => true, 'is_active' => true]);

        return compact('company', 'branch', 'warehouse');
    }

    /** Crea un usuario autenticado con el rol indicado. */
    protected function actingAsRole(string $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $user = User::factory()->create([
            'company_id' => $company?->id,
            'branch_id' => $branch?->id,
            'is_active' => true,
        ]);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }
}
