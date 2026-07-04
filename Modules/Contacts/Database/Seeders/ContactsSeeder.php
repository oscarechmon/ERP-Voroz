<?php

declare(strict_types=1);

namespace Modules\Contacts\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Contacts\Models\Customer;
use Modules\Contacts\Models\Supplier;
use Modules\Settings\Models\Company;

/** Siembra clientes y proveedores demo. */
class ContactsSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = Company::value('id');

        // Cliente genérico "Público general" para ventas rápidas en el POS.
        Customer::firstOrCreate(
            ['doc_type' => 'DNI', 'doc_number' => '00000000'],
            ['company_id' => $companyId, 'name' => 'Público general', 'is_active' => true],
        );

        if (Customer::count() < 5) {
            Customer::factory(40)->create(['company_id' => $companyId]);
        }

        if (Supplier::count() === 0) {
            Supplier::factory(15)->create(['company_id' => $companyId]);
        }
    }
}
