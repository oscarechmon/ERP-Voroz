<?php

declare(strict_types=1);

namespace Modules\Sales\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Modules\Catalog\Models\Product;
use Modules\Contacts\Models\Customer;
use Modules\Inventory\Models\Stock;
use Modules\Sales\Models\Sale;
use Modules\Sales\Services\SaleService;
use Modules\Settings\Models\Warehouse;

/**
 * Genera ventas de demostración repartidas en los últimos 30 días para poblar el
 * dashboard y los reportes. Usa el flujo real de checkout (descuenta stock y
 * genera kardex), autenticándose como el administrador.
 */
class SalesSeeder extends Seeder
{
    public function run(): void
    {
        if (Sale::count() > 0) {
            return;
        }

        $admin = User::where('email', 'admin@sistema.test')->first();
        $warehouse = Warehouse::where('is_default', true)->first();
        if (! $admin || ! $warehouse) {
            return;
        }

        Auth::login($admin);
        $service = app(SaleService::class);

        // Productos con stock disponible.
        $productIds = Stock::where('quantity', '>', 5)->pluck('product_id')->all();
        $products = Product::whereIn('id', $productIds)->get();
        $customers = Customer::all();

        if ($products->isEmpty()) {
            return;
        }

        $docTypes = ['ticket', 'ticket', 'ticket', 'boleta', 'boleta', 'factura'];
        $methods = ['efectivo', 'efectivo', 'yape', 'plin', 'tarjeta'];

        for ($i = 0; $i < 40; $i++) {
            $lineCount = random_int(1, 4);
            $items = [];
            foreach ($products->random(min($lineCount, $products->count())) as $product) {
                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => random_int(1, 3),
                    'price' => (float) $product->price,
                ];
            }

            $total = array_sum(array_map(fn ($it) => $it['quantity'] * $it['price'], $items));

            try {
                $sale = $service->checkout([
                    'doc_type' => $docTypes[array_rand($docTypes)],
                    'customer_id' => $customers->isNotEmpty() ? $customers->random()->id : null,
                    'warehouse_id' => $warehouse->id,
                    'items' => $items,
                    'payments' => [['method' => $methods[array_rand($methods)], 'amount' => round($total, 2)]],
                ]);

                // Distribuye la fecha en los últimos 30 días (para gráficos del dashboard).
                $date = now()->subDays(random_int(0, 29))->subHours(random_int(0, 12));
                $sale->forceFill(['sold_at' => $date, 'created_at' => $date])->saveQuietly();
            } catch (\Throwable) {
                // Ignora ventas que fallen por stock puntual y continúa el seeding.
                continue;
            }
        }

        Auth::logout();
    }
}
