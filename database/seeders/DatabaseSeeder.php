<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Catalog\Database\Seeders\CatalogSeeder;
use Modules\Contacts\Database\Seeders\ContactsSeeder;
use Modules\Inventory\Database\Seeders\StockSeeder;
use Modules\Sales\Database\Seeders\SalesSeeder;
use Modules\Settings\Database\Seeders\CompanySeeder;
use Modules\Users\Database\Seeders\RolePermissionSeeder;
use Modules\Users\Database\Seeders\UserSeeder;

/**
 * Orquestador de seeders. El orden importa: primero permisos/roles y la empresa,
 * luego los usuarios (que dependen de ambos).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CompanySeeder::class,
            UserSeeder::class,
            CatalogSeeder::class,
            ContactsSeeder::class,
            StockSeeder::class,
            SalesSeeder::class,
        ]);
    }
}
