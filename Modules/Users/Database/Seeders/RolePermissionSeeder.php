<?php

declare(strict_types=1);

namespace Modules\Users\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Genera la matriz completa de permisos (modulo.accion) y los 7 roles base del
 * sistema, asignando a cada rol su conjunto de permisos. El sistema permite crear
 * roles adicionales desde la UI; estos son sólo el punto de partida.
 */
class RolePermissionSeeder extends Seeder
{
    /** Módulos y las acciones disponibles en cada uno. */
    private const MODULES = [
        'dashboard' => ['view'],
        'products' => ['view', 'create', 'edit', 'delete', 'export', 'import', 'print'],
        'categories' => ['view', 'create', 'edit', 'delete'],
        'brands' => ['view', 'create', 'edit', 'delete'],
        'units' => ['view', 'create', 'edit', 'delete'],
        'customers' => ['view', 'create', 'edit', 'delete', 'export'],
        'suppliers' => ['view', 'create', 'edit', 'delete', 'export'],
        'stock' => ['view', 'export'],
        'inventory' => ['view', 'create', 'edit', 'delete', 'export'],
        'sales' => ['view', 'create', 'edit', 'delete', 'export', 'print'],
        'purchases' => ['view', 'create', 'edit', 'delete', 'export'],
        'cashbox' => ['view', 'create', 'edit', 'export'],
        'reports' => ['view', 'export'],
        'users' => ['view', 'create', 'edit', 'delete'],
        'roles' => ['view', 'create', 'edit', 'delete'],
        'settings' => ['view', 'edit'],
        'audit' => ['view'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1) Crear todos los permisos.
        $all = [];
        foreach (self::MODULES as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                $all[] = $name;
            }
        }

        // 2) Crear roles.
        $roles = [
            'Super Administrador',
            'Administrador',
            'Supervisor',
            'Ventas',
            'Logística',
            'Caja',
            'Invitado',
        ];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // 3) Asignar permisos por rol.
        // Super Administrador: acceso total (además el código lo trata como comodín).
        Role::findByName('Super Administrador')->syncPermissions($all);
        Role::findByName('Administrador')->syncPermissions($all);

        Role::findByName('Supervisor')->syncPermissions($this->only([
            'dashboard', 'products', 'categories', 'brands', 'stock', 'inventory',
            'sales', 'purchases', 'customers', 'suppliers', 'reports', 'audit',
        ], ['view', 'export']));

        Role::findByName('Ventas')->syncPermissions([
            'dashboard.view',
            'products.view', 'products.print',
            'customers.view', 'customers.create', 'customers.edit',
            'sales.view', 'sales.create', 'sales.print',
            'stock.view',
        ]);

        Role::findByName('Logística')->syncPermissions([
            'dashboard.view',
            'products.view', 'products.create', 'products.edit', 'products.import', 'products.export', 'products.print',
            'categories.view', 'categories.create', 'categories.edit',
            'brands.view', 'brands.create', 'brands.edit',
            'stock.view', 'stock.export',
            'inventory.view', 'inventory.create', 'inventory.edit',
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'purchases.view', 'purchases.create',
        ]);

        Role::findByName('Caja')->syncPermissions([
            'dashboard.view',
            'sales.view', 'sales.create', 'sales.print',
            'cashbox.view', 'cashbox.create', 'cashbox.edit', 'cashbox.export',
            'customers.view', 'customers.create',
        ]);

        Role::findByName('Invitado')->syncPermissions(['dashboard.view']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Devuelve los permisos "modulo.accion" para los módulos y acciones dados. */
    private function only(array $modules, array $actions): array
    {
        $result = [];
        foreach ($modules as $module) {
            foreach (self::MODULES[$module] ?? [] as $action) {
                if (in_array($action, $actions, true)) {
                    $result[] = "{$module}.{$action}";
                }
            }
        }

        return array_values(array_unique($result));
    }
}
