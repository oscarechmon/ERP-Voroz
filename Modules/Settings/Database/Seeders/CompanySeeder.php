<?php

declare(strict_types=1);

namespace Modules\Settings\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Settings\Models\Branch;
use Modules\Settings\Models\Company;
use Modules\Settings\Models\Warehouse;

/** Crea la empresa demo con una sucursal principal y su almacén por defecto. */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['ruc' => '20123456789'],
            [
                'business_name' => 'Voroz Comercial S.A.C.',
                'trade_name' => 'Voroz Store',
                'address' => 'Av. Principal 123, Lima, Perú',
                'phone' => '(01) 555-1234',
                'email' => 'ventas@voroz.test',
                'currency' => 'PEN',
                'currency_symbol' => 'S/',
                'igv_percent' => 18.00,
                'prices_include_igv' => true,
                'is_active' => true,
            ],
        );

        $branch = Branch::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'PRIN'],
            ['name' => 'Sucursal Principal', 'address' => $company->address, 'is_main' => true, 'is_active' => true],
        );

        Warehouse::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'ALM01'],
            ['name' => 'Almacén Central', 'is_default' => true, 'is_active' => true],
        );
    }
}
