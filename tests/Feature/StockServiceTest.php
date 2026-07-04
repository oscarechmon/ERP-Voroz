<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Exceptions\BusinessException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Services\StockService;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;
    private Product $product;
    private int $warehouseId;

    protected function setUp(): void
    {
        parent::setUp();
        $env = $this->seedErp();
        $this->warehouseId = $env['warehouse']->id;
        $this->service = app(StockService::class);
        $this->product = Product::factory()->create(['cost' => 10, 'price' => 20]);
    }

    public function test_entry_calculates_weighted_average_cost(): void
    {
        $this->service->entry($this->product->id, $this->warehouseId, 100, 10.0);
        $this->service->entry($this->product->id, $this->warehouseId, 100, 20.0);

        $stock = $this->product->stocks()->where('warehouse_id', $this->warehouseId)->first();

        $this->assertEquals(200, (float) $stock->quantity);
        $this->assertEquals(15.0, (float) $stock->avg_cost); // (100*10 + 100*20) / 200
    }

    public function test_exit_reduces_stock_and_records_kardex_balance(): void
    {
        $this->service->entry($this->product->id, $this->warehouseId, 50, 10.0);
        $movement = $this->service->exit($this->product->id, $this->warehouseId, 20);

        $this->assertEquals(30, (float) $movement->balance);
        $this->assertEquals(-20, (float) $movement->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'balance' => 30,
        ]);
    }

    public function test_exit_throws_when_stock_is_insufficient(): void
    {
        $this->service->entry($this->product->id, $this->warehouseId, 5, 10.0);

        $this->expectException(BusinessException::class);
        $this->service->exit($this->product->id, $this->warehouseId, 10);
    }

    public function test_adjust_sets_absolute_quantity_and_records_difference(): void
    {
        $this->service->entry($this->product->id, $this->warehouseId, 100, 10.0);
        $movement = $this->service->adjust($this->product->id, $this->warehouseId, 70);

        $this->assertEquals(-30, (float) $movement->quantity);
        $this->assertEquals(70, (float) $this->service->available($this->product->id, $this->warehouseId));
    }
}
