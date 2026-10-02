<?php

declare(strict_types=1);

namespace Modules\Users\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Settings\Models\Branch;
use Modules\Settings\Models\Company;

/**
 * Crea usuarios de demostración, uno por rol, asociados a la empresa/sucursal.
 * Todos usan la contraseña "password" (sólo para entorno de desarrollo).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        $branch = Branch::where('company_id', $company?->id)->first();

        $users = [
            ['name' => 'Super Administrador', 'email' => 'admin@sistema.test', 'role' => 'Super Administrador'],
            ['name' => 'Ana Administradora', 'email' => 'admin2@sistema.test', 'role' => 'Administrador'],
            ['name' => 'Sergio Supervisor', 'email' => 'supervisor@sistema.test', 'role' => 'Supervisor'],
            ['name' => 'Vanesa Ventas', 'email' => 'ventas@sistema.test', 'role' => 'Ventas'],
            ['name' => 'Luis Logística', 'email' => 'logistica@sistema.test', 'role' => 'Logística'],
            ['name' => 'Carla Caja', 'email' => 'caja@sistema.test', 'role' => 'Caja'],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'company_id' => $company?->id,
                    'branch_id' => $branch?->id,
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$data['role']]);
        }
    }
}
