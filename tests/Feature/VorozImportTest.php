<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Contacts\Models\Customer;
use Modules\Integration\Services\SqlDumpReader;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Sales\Models\Sale;
use Modules\Sales\Services\SaleService;
use Tests\TestCase;

/**
 * Importación de clientes y ventas del ERP anterior (voroz) desde su volcado
 * SQL. Los datos de este volcado son inventados.
 */
class VorozImportTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    /** @var array<string, mixed> */
    private array $env;

    private const DUMP = <<<'SQL'
        -- phpMyAdmin SQL Dump
        INSERT INTO `customers` (`id`, `company_id`, `doc_type`, `doc_number`, `name`, `email`, `phone`, `address`, `notes`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
        (1, 1, 'DNI', '11112222', 'Ana O''Brien', 'ana@example.com', '900000001', 'Av. Uno 1', 'Cliente normal', 1, '2026-07-04 20:37:27', '2026-07-04 20:37:27', NULL),
        (2, 1, 'DNI', NULL, 'LUIS SIN DNI', NULL, '900000002', NULL, NULL, 1, '2026-07-20 15:22:39', '2026-07-20 15:22:39', NULL);

        INSERT INTO `products` (`id`, `company_id`, `code`, `name`, `cost`, `price`, `deleted_at`) VALUES
        (1, 1, 'dasd', 'BPL 1', 54.00, 60.00, '2026-07-16 16:23:53'),
        (2, 1, 'PRD-000001', 'BPL1 SLIMFLORA', 54.00, 160.00, NULL),
        (3, 1, 'PRD-000009', 'Producto que ya no existe', 1.00, 1.00, NULL);

        INSERT INTO `sales` (`id`, `company_id`, `branch_id`, `warehouse_id`, `customer_id`, `user_id`, `doc_type`, `series`, `number`, `full_number`, `subtotal`, `tax`, `discount`, `total`, `tax_percent`, `paid`, `change`, `status`, `payment_status`, `notes`, `sold_at`, `cancelled_at`, `cancelled_by`, `cancel_reason`, `created_at`, `updated_at`, `deleted_at`) VALUES
        (1, 1, 1, 1, NULL, 1, 'ticket', 'T001', 1, 'T001-00000001', 135.59, 24.41, 0.00, 160.00, 18.00, 160.00, 0.00, 'cancelled', 'cancelled', 'Promo; 2 por \'160\'', '2026-07-06 17:13:21', '2026-07-24 15:02:37', 1, 'reembolso', '2026-07-06 17:13:21', '2026-07-24 15:02:37', NULL),
        (2, 1, 1, 1, 2, 1, 'ticket', 'T001', 2, 'T001-00000002', 203.39, 36.61, 0.00, 240.00, 18.00, 240.00, 0.00, 'completed', 'paid', NULL, '2026-07-20 15:23:36', NULL, NULL, NULL, '2026-07-20 15:23:36', '2026-07-20 15:23:36', NULL),
        (3, 1, 1, 1, 1, 1, 'boleta', 'B001', 1, 'B001-00000001', 67.80, 12.20, 0.00, 80.00, 18.00, 80.00, 0.00, 'completed', 'paid', NULL, '2026-07-21 16:36:49', NULL, NULL, NULL, '2026-07-21 16:36:49', '2026-07-21 16:36:49', NULL);

        INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `description`, `quantity`, `price`, `cost`, `discount`, `tax`, `subtotal`, `created_at`, `updated_at`) VALUES
        (1, 1, 1, 'BPL 1', 1.00, 160.00, 54.00, 0.00, 24.41, 160.00, '2026-07-06 17:13:21', '2026-07-06 17:13:21'),
        (2, 2, 2, 'BPL1 SLIMFLORA', 3.00, 80.00, 54.00, 0.00, 36.61, 240.00, '2026-07-20 15:23:36', '2026-07-20 15:23:36'),
        (3, 3, 3, 'Producto que ya no existe', 1.00, 80.00, 1.00, 0.00, 12.20, 80.00, '2026-07-21 16:36:49', '2026-07-21 16:36:49');

        INSERT INTO `sale_payments` (`id`, `sale_id`, `method`, `amount`, `reference`, `created_at`, `updated_at`) VALUES
        (1, 1, 'yape', 160.00, NULL, '2026-07-06 17:13:21', '2026-07-06 17:13:21'),
        (2, 2, 'efectivo', 240.00, NULL, '2026-07-20 15:23:36', '2026-07-20 15:23:36'),
        (3, 3, 'efectivo', 80.00, NULL, '2026-07-21 16:36:49', '2026-07-21 16:36:49');
        COMMIT;
        SQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = $this->seedErp();

        $this->file = tempnam(sys_get_temp_dir(), 'voroz').'.sql';
        file_put_contents($this->file, self::DUMP);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_el_lector_resuelve_textos_escapados_y_nulos(): void
    {
        $sales = SqlDumpReader::fromString(self::DUMP)->rows('sales');

        $this->assertCount(3, $sales);
        $this->assertSame("Promo; 2 por '160'", $sales[0]['notes']);
        $this->assertNull($sales[1]['notes']);
        $this->assertSame("Ana O'Brien", SqlDumpReader::fromString(self::DUMP)->rows('customers')[0]['name']);
    }

    public function test_importa_clientes_y_ventas_sin_mover_stock_y_sin_duplicar(): void
    {
        $bpl = Product::factory()->create(['code' => 'WEB-P-22', 'name' => 'BPL1 SLIMFLORA 210mL']);
        $ana = Customer::factory()->create(['doc_type' => 'DNI', 'doc_number' => '11112222', 'phone' => '911111111', 'address' => null]);

        $this->artisan('voroz:importar', ['archivo' => $this->file])->assertSuccessful();
        $this->artisan('voroz:importar', ['archivo' => $this->file])->assertSuccessful();

        $this->assertSame(2, Customer::count(), 'Ana se enlaza por DNI; Luis (sin DNI ni correo) no se duplica al repetir.');
        $ana->refresh();
        $this->assertSame('911111111', $ana->phone, 'Lo que ya tenía la ficha no se pisa.');
        $this->assertSame('Av. Uno 1', $ana->address, 'Lo vacío se completa.');

        $this->assertSame(3, Sale::count());
        $cancelled = Sale::where('full_number', 'T001-00000001')->sole();
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('reembolso', $cancelled->cancel_reason);
        $this->assertSame($bpl->id, $cancelled->items->first()->product_id, 'BPL 1 (borrado en voroz) es el BPL1 de aquí.');

        $boleta = Sale::where('full_number', 'B001-00000001')->sole();
        $this->assertSame('boleta', $boleta->doc_type);
        $this->assertSame($ana->id, $boleta->customer_id);
        $this->assertNull($boleta->items->first()->product_id, 'Lo que no se reconoce queda como descripción.');
        $this->assertSame('2026-07-21 16:36:49', $boleta->sold_at->format('Y-m-d H:i:s'));

        $this->assertSame(0, InventoryMovement::count(), 'Las ventas importadas son historia: no mueven stock.');
    }

    public function test_el_pos_sigue_la_numeracion_de_voroz(): void
    {
        $this->actingAsRole('Caja', $this->env['company'], $this->env['branch']);
        $this->artisan('voroz:importar', ['archivo' => $this->file])->assertSuccessful();

        $sale = app(SaleService::class)->checkout([
            'doc_type' => 'ticket',
            'items' => [['product_id' => Product::factory()->service()->create(['price' => 50])->id, 'quantity' => 1]],
            'payments' => [['method' => 'efectivo', 'amount' => 50]],
        ]);

        $this->assertSame('T001-00000003', $sale->full_number);
    }

    public function test_simular_no_guarda_nada(): void
    {
        $this->artisan('voroz:importar', ['archivo' => $this->file, '--simular' => true])
            ->expectsOutputToContain('SIMULACIÓN')
            ->assertSuccessful();

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Customer::count());
    }

    public function test_un_archivo_que_no_existe_no_importa_nada(): void
    {
        $this->artisan('voroz:importar', ['archivo' => 'no/existe.sql'])->assertFailed();
    }
}
