<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\Product;
use Modules\Inventory\Services\StockService;
use Modules\Sales\Models\Sale;
use Modules\Sales\Services\SaleService;
use Tests\TestCase;

/**
 * Cifras del dashboard: solo ventas completadas y vigentes, cada una en su día,
 * mes y año. El resultado se reutiliza un minuto, pero una venta nueva lo
 * deja al día.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private array $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = $this->seedErp();
        $this->actingAsRole('Administrador', $this->env['company'], $this->env['branch']);
    }

    private function product(string $name, float $price, float $cost): Product
    {
        $product = Product::factory()->create(['name' => $name, 'price' => $price, 'cost' => $cost, 'is_active' => true]);
        app(StockService::class)->entry($product->id, $this->env['warehouse']->id, 100, $cost);

        return $product;
    }

    private function sell(Product $product, int $quantity, ?Carbon $soldAt = null): Sale
    {
        $sale = app(SaleService::class)->checkout([
            'doc_type' => 'ticket',
            'warehouse_id' => $this->env['warehouse']->id,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            'payments' => [['method' => 'efectivo', 'amount' => (float) $product->price * $quantity]],
        ]);

        if ($soldAt) {
            $sale->forceFill(['sold_at' => $soldAt])->saveQuietly();
        }

        return $sale;
    }

    public function test_las_cifras_cuentan_solo_lo_vendido_en_cada_periodo(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));
        $crema = $this->product('Crema', 50, 20);

        $this->sell($crema, 2);                                              // hoy: 100
        $this->sell($crema, 1, Carbon::parse('2026-10-15 00:00:00'));        // hoy, primer segundo: 50
        $this->sell($crema, 1, Carbon::parse('2026-10-14 23:59:59'));        // ayer, este mes: 50
        $this->sell($crema, 1, Carbon::parse('2026-09-30 23:59:59'));        // mes pasado, este año: 50
        $this->sell($crema, 1, Carbon::parse('2025-12-31 23:00:00'));        // año pasado: 50
        app(SaleService::class)->cancel($this->sell($crema, 3));             // anulada: no cuenta
        $this->sell($crema, 4)->delete();                                    // eliminada: no cuenta

        $m = $this->getJson('/api/v1/dashboard/metrics')->assertOk()->json('data');

        $this->assertEquals(150, $m['sales_today']);
        $this->assertSame(2, $m['sales_count_today']);
        $this->assertEquals(200, $m['sales_month']);
        $this->assertEquals(250, $m['sales_year']);
        $this->assertEquals(4, $m['products_sold'], 'Unidades vendidas en el mes.');
        $this->assertEquals(90, $m['profit_today'], '3 unidades × (50 − 20).');
        $this->assertEquals(120, $m['profit_month']);
        $this->assertEquals(150, collect($m['sales_by_day'])->firstWhere('date', '15/10')['total']);
        $this->assertEquals(6, $m['top_products'][0]['qty'], 'El historial completo, sin anuladas ni eliminadas.');

        Carbon::setTestNow();
    }

    public function test_una_venta_nueva_se_ve_al_instante(): void
    {
        $crema = $this->product('Crema', 50, 20);
        $gel = $this->product('Gel', 30, 10);
        $this->sell($crema, 2);

        $this->getJson('/api/v1/dashboard/metrics')->assertOk()->assertJsonPath('data.top_products.0.name', 'Crema');

        $this->sell($gel, 5);

        $this->getJson('/api/v1/dashboard/metrics')->assertOk()
            ->assertJsonPath('data.sales_today', 250)
            ->assertJsonPath('data.top_products.0.name', 'Gel')
            ->assertJsonPath('data.top_products.0.qty', 5);
    }
}
