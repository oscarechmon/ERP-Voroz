<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cashbox\Services\CashboxService;
use Modules\Catalog\Models\Product;
use Modules\Contacts\Models\Supplier;
use Modules\Inventory\Services\StockService;
use Modules\Purchases\Services\PurchaseService;
use Tests\TestCase;

class PurchaseAndCashboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_increases_stock_and_recomputes_average_cost(): void
    {
        $env = $this->seedErp();
        $this->actingAsRole('Logística', $env['company'], $env['branch']);
        $warehouseId = $env['warehouse']->id;

        $product = Product::factory()->create(['cost' => 10]);
        app(StockService::class)->entry($product->id, $warehouseId, 5, 10); // 5 @ 10
        $supplier = Supplier::create(['name' => 'Proveedor Test', 'doc_type' => 'RUC']);

        app(PurchaseService::class)->register([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouseId,
            'items' => [['product_id' => $product->id, 'quantity' => 100, 'cost' => 50]],
        ]);

        $stock = $product->stocks()->where('warehouse_id', $warehouseId)->first();
        $this->assertEquals(105, (float) $stock->quantity);
        // (5*10 + 100*50) / 105 = 5050/105 = 48.0952...
        $this->assertEqualsWithDelta(48.10, (float) $stock->avg_cost, 0.02);
    }

    public function test_cashbox_open_close_computes_arqueo_difference(): void
    {
        $env = $this->seedErp();
        $user = $this->actingAsRole('Caja', $env['company'], $env['branch']);
        $service = app(CashboxService::class);

        $service->open($user->id, null, 100);
        $service->movement($user->id, 'income', 50, 'Propina');
        $service->movement($user->id, 'expense', 30, 'Gasto');

        // Esperado = 100 + 0 ventas efectivo + 50 - 30 = 120. Contado 125 => diferencia +5.
        $session = $service->close($user->id, 125);

        $this->assertEquals(120.0, (float) $session->expected_amount);
        $this->assertEquals(5.0, (float) $session->difference);
        $this->assertEquals('closed', $session->status);
    }

    public function test_cannot_open_two_cash_sessions(): void
    {
        $env = $this->seedErp();
        $user = $this->actingAsRole('Caja', $env['company'], $env['branch']);
        $service = app(CashboxService::class);
        $service->open($user->id, null, 100);

        $this->expectException(\App\Core\Exceptions\BusinessException::class);
        $service->open($user->id, null, 200);
    }
}
