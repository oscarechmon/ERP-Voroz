<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Modules\Agenda\Models\Appointment;
use Modules\Attendances\Models\Attendance;
use Modules\Cashbox\Models\CashSession;
use Modules\Catalog\Models\Product;
use Modules\Commissions\Models\Commission;
use Modules\Contacts\Models\Customer;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Services\StockService;
use Modules\OnlineOrders\Models\OnlineOrder;
use Modules\OnlineOrders\Services\OnlineOrderService;
use Modules\Packages\Models\CustomerPackage;
use Modules\Packages\Models\Package;
use Modules\Sales\Models\Sale;
use Modules\Settings\Models\Warehouse;
use Modules\Staff\Models\Employee;
use Tests\TestCase;

/**
 * Todo lo de la web pasa al sistema: clientes de la tienda, pedidos online
 * (con su seguimiento de vuelta a la web) y el historial completo del panel.
 */
class WebMigrationTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = $this->seedErp()['warehouse'];
        config(['integration.token' => 'secreto-de-prueba', 'integration.web_url' => null]);
    }

    private function api(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->withHeader('X-Integration-Token', 'secreto-de-prueba')
            ->json($method, "/api/v1/integration/{$uri}", $data);
    }

    private function import(string $kind, array $records): TestResponse
    {
        return $this->api('POST', "import/{$kind}", ['records' => $records])->assertOk();
    }

    private function productWithStock(float $quantity, array $attributes = []): Product
    {
        $product = Product::factory()->create(['is_active' => true] + $attributes);
        app(StockService::class)->entry($product->id, $this->warehouse->id, $quantity, 10);

        return $product;
    }

    private function stockOf(Product $product): float
    {
        return app(StockService::class)->available($product->id, $this->warehouse->id);
    }

    /** @return array<string, mixed> */
    private function order(Product $product, string $status, array $extra = []): array
    {
        return $extra + [
            'web_id' => 17,
            'code' => 'W-000017',
            'status' => $status,
            'fulfillment' => 'delivery',
            'recipient_name' => 'Oscar Echegaray',
            'phone' => '955013680',
            'address' => 'Av. Siempre Viva 123',
            'district' => 'Miraflores',
            'subtotal' => 100,
            'delivery_fee' => 10,
            'total' => 110,
            'gateway' => 'izipay',
            'payment_reference' => 'TX-1',
            'paid_at' => $status === 'paid' ? now()->toIso8601String() : null,
            'ordered_at' => now()->toIso8601String(),
            'customer' => ['web_id' => 3, 'code' => 'CLI-000003', 'name' => 'Oscar Echegaray', 'document_number' => '72164814', 'email' => 'oscar@example.com'],
            'items' => [['product_id' => $product->id, 'item_type' => 'product', 'name' => $product->name, 'unit_price' => 50, 'quantity' => 2, 'subtotal' => 100]],
            'history' => [['status' => 'pending_payment', 'happened_at' => now()->subMinute()->toIso8601String()]],
        ];
    }

    // ----------------------------------------------------- Clientes de la tienda

    public function test_un_cliente_de_la_tienda_se_enlaza_sin_duplicarse(): void
    {
        $existing = Customer::factory()->create(['doc_number' => '72164814', 'doc_type' => 'DNI']);

        $this->api('POST', 'customers', ['web_id' => 3, 'name' => 'Oscar Echegaray', 'document_number' => '72164814', 'email' => 'o@example.com'])
            ->assertOk()->assertJsonPath('data.id', $existing->id);

        $id = $this->api('POST', 'customers', ['web_id' => 9, 'code' => 'CLI-000009', 'name' => 'Nueva', 'email' => 'nueva@example.com'])
            ->assertOk()->assertJsonPath('data.code', 'CLI-000009')->json('data.id');
        $this->api('POST', 'customers', ['web_id' => 9, 'name' => 'Nueva Apellido', 'email' => 'nueva@example.com'])
            ->assertOk()->assertJsonPath('data.id', $id);

        $this->assertSame(2, Customer::count());
        $this->assertSame('Nueva Apellido', Customer::find($id)->name);
    }

    // --------------------------------------------------------- Pedidos online

    public function test_un_pedido_pagado_genera_su_venta_una_vez_y_descuenta_stock(): void
    {
        $crema = $this->productWithStock(10);

        $this->api('POST', 'orders', $this->order($crema, 'pending_payment'))->assertOk()->assertJsonPath('data.sale', null);
        $this->assertEquals(10.0, $this->stockOf($crema), 'Sin cobro no hay venta.');

        $this->api('POST', 'orders', $this->order($crema, 'paid'))->assertOk()->assertJsonPath('data.stock.'.$crema->id, 8);
        $this->api('POST', 'orders', $this->order($crema, 'paid'))->assertOk();

        $order = OnlineOrder::where('code', 'W-000017')->sole();
        $this->assertSame('paid', $order->status);
        $this->assertSame(1, Sale::where('external_reference', 'W-000017')->count());
        $this->assertSame('Oscar Echegaray', $order->customer->name);
        $this->assertEquals(8.0, $this->stockOf($crema));
    }

    public function test_el_seguimiento_es_del_sistema_y_se_avisa_a_la_web(): void
    {
        config(['integration.web_url' => 'https://web.test']);
        Http::fake(['web.test/*' => Http::response(['success' => true])]);

        $crema = $this->productWithStock(10);
        $this->api('POST', 'orders', $this->order($crema, 'paid'));
        $order = OnlineOrder::where('code', 'W-000017')->sole();

        $this->actingAsRole('Recepción');
        $this->postJson("/api/v1/online-orders/{$order->id}/status", ['status' => 'shipped'])->assertStatus(422);
        $this->postJson("/api/v1/online-orders/{$order->id}/status", ['status' => 'preparing', 'note' => 'Empacando'])->assertOk();
        $this->app->terminate();

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://web.test/erp/pedidos/W-000017/estado'
            && $r['status'] === 'preparing' && $r['note'] === 'Empacando');

        // La web vuelve a mandar el pedido (p. ej. al reintentar): no pisa el seguimiento.
        $this->api('POST', 'orders', $this->order($crema, 'paid'));
        $this->assertSame('preparing', $order->fresh()->status);

        $statuses = $this->api('GET', 'orders/statuses?codes[]=W-000017')->assertOk()->json('data.0');
        $this->assertSame('preparing', $statuses['status']);
        $this->assertSame('Empacando', $statuses['history'][0]['note']);
    }

    public function test_anular_un_pedido_pagado_anula_la_venta_y_devuelve_el_stock(): void
    {
        $crema = $this->productWithStock(10);
        $this->api('POST', 'orders', $this->order($crema, 'paid'));
        $order = OnlineOrder::where('code', 'W-000017')->sole();

        $this->actingAsRole('Recepción');
        app(OnlineOrderService::class)->changeStatus($order, 'cancelled', 'Sin stock para enviar');

        $this->assertSame('cancelled', Sale::where('external_reference', 'W-000017')->value('status'));
        $this->assertEquals(10.0, $this->stockOf($crema));
    }

    public function test_un_pedido_historico_trae_su_venta_sin_mover_stock(): void
    {
        $crema = $this->productWithStock(10);

        $this->api('POST', 'orders', ['historical' => true] + $this->order($crema, 'paid'))->assertOk();

        $sale = Sale::where('external_reference', 'W-000017')->sole();
        $this->assertSame('W-000017', $sale->full_number);
        $this->assertEquals(110.0, (float) $sale->total, 'Incluye el delivery.');
        $this->assertSame('paid', $sale->payment_status);
        $this->assertEquals(10.0, $this->stockOf($crema), 'El stock importado ya tenía este pedido descontado.');
    }

    // -------------------------------------------------------------- Catálogo

    public function test_el_catalogo_trae_las_sesiones_y_servicios_de_cada_paquete(): void
    {
        $this->actingAsRole('Administrador');
        $facial = Product::factory()->service()->create(['is_active' => true]);
        $id = $this->postJson('/api/v1/packages', ['name' => '6 faciales', 'price' => 500, 'total_sessions' => 6, 'service_ids' => [$facial->id]])
            ->assertCreated()->json('data.product_id');

        $item = collect($this->api('GET', 'catalog')->json('data'))->firstWhere('id', $id);

        $this->assertSame('package', $item['type']);
        $this->assertSame(['total_sessions' => 6, 'validity_days' => null, 'service_ids' => [$facial->id]], $item['package']);
    }

    // ------------------------------------------------------ Historial del panel

    public function test_el_historial_del_panel_se_importa_completo_y_sin_duplicar(): void
    {
        $facial = Product::factory()->service()->create(['name' => 'Radiofrecuencia', 'price' => 199, 'is_active' => true]);
        $gel = $this->productWithStock(5, ['name' => 'Gel']);

        $users = [['id' => 1, 'name' => 'Admin Web', 'email' => 'admin@web.test', 'password_hash' => Hash::make('secreta-123'), 'roles' => ['Recepción'], 'active' => true]];
        $this->import('users', $users)->assertJsonPath('data.created', 1);

        $this->import('employees', [['id' => 5, 'name' => 'Rosa', 'user_id' => 1, 'service_ids' => [$facial->id], 'active' => true]]);
        $this->import('customers', [['id' => 3, 'code' => 'CLI-000003', 'full_name' => 'Oscar Echegaray', 'document_number' => '72164814',
            'birth_date' => '1990-05-01', 'gender' => 'M', 'allergies' => 'Látex', 'observations' => 'Piel sensible', 'active' => true]]);
        $this->import('commission_rules', [['id' => 1, 'employee_id' => 5, 'service_id' => $facial->id, 'type' => 'percentage', 'value' => 10, 'active' => true]]);

        $packageProductId = $this->import('packages', [['id' => 2, 'name' => '4 radiofrecuencias', 'price' => 600, 'total_sessions' => 4, 'service_ids' => [$facial->id], 'active' => true]])
            ->json('data.links.2');

        $this->import('sales', [[
            'id' => 1, 'code' => 'V-000001', 'client_id' => 3, 'discount' => 0, 'status' => 'partial', 'created_by' => 1,
            'created_at' => '2026-08-31 02:58:00',
            'items' => [
                ['type' => 'package', 'package_id' => 2, 'description' => '4 radiofrecuencias', 'unit_price' => 600, 'quantity' => 1, 'subtotal' => 600],
                ['type' => 'service', 'product_id' => $facial->id, 'description' => 'Radiofrecuencia', 'unit_price' => 199, 'quantity' => 1, 'subtotal' => 199, 'employee_id' => 5],
            ],
            'payments' => [['method' => 'cash', 'amount' => 500, 'paid_at' => '2026-08-31 03:00:00', 'created_by' => 1]],
        ]]);
        $this->import('customer_packages', [['id' => 7, 'client_id' => 3, 'package_id' => 2, 'sale_id' => 1, 'package_name' => '4 radiofrecuencias',
            'price' => 600, 'total_sessions' => 4, 'used_sessions' => 1, 'purchased_at' => '2026-08-31', 'status' => 'active']]);
        $this->import('appointments', [['id' => 4, 'client_id' => 3, 'service_id' => $facial->id, 'employee_id' => 5,
            'appointment_date' => '2026-09-01', 'start_time' => '10:00', 'end_time' => '11:00', 'status' => 'attended']]);
        $this->import('attendances', [['id' => 8, 'client_id' => 3, 'service_id' => $facial->id, 'employee_id' => 5, 'appointment_id' => 4,
            'client_package_id' => 7, 'session_number' => 1, 'attended_at' => '2026-09-01', 'created_by' => 1,
            'supplies' => [['product_id' => $gel->id, 'quantity' => 1]]]]);
        $this->import('commissions', [['id' => 1, 'employee_id' => 5, 'attendance_id' => 8, 'service_id' => $facial->id, 'base_amount' => 150,
            'type' => 'percentage', 'value' => 10, 'amount' => 15, 'status' => 'pending', 'generated_at' => '2026-09-01']]);
        $this->import('cash_sessions', [['id' => 1, 'opening_amount' => 50, 'expected_amount' => 540, 'counted_amount' => 540, 'difference' => 0,
            'status' => 'closed', 'opened_at' => '2026-08-31 01:00:00', 'closed_at' => '2026-08-31 20:00:00', 'opened_by' => 1,
            'movements' => [
                ['type' => 'opening', 'payment_method' => null, 'amount' => 50, 'description' => 'Apertura'],
                ['type' => 'sale', 'payment_method' => 'cash', 'amount' => 500, 'description' => 'Venta V-000001'],
                ['type' => 'expense', 'payment_method' => null, 'amount' => -10, 'description' => 'Taxi', 'created_by' => 1],
            ]]]);

        // Usuario: entra con su contraseña de la web y conserva su rol.
        $user = User::where('email', 'admin@web.test')->sole();
        $this->assertTrue(Hash::check('secreta-123', $user->password));
        $this->assertTrue($user->hasRole('Recepción'));

        $employee = Employee::sole();
        $this->assertSame($user->id, $employee->user_id);

        $customer = Customer::sole();
        $this->assertSame('CLI-000003', $customer->code);
        $this->assertSame('Látex', $customer->allergies);
        $this->assertSame('Piel sensible', $customer->notes);

        $this->assertSame(Package::sole()->product_id, $packageProductId, 'La web enlaza el paquete por el id de su producto.');

        $sale = Sale::where('external_reference', 'V-000001')->sole();
        $this->assertSame(Sale::CHANNEL_WEB_PANEL, $sale->channel);
        $this->assertEquals(799.0, (float) $sale->total);
        $this->assertEquals(299.0, $sale->balance());
        $this->assertSame('efectivo', $sale->payments->first()->method);
        $this->assertSame($user->id, $sale->user_id);
        $this->assertSame($employee->id, $sale->items->firstWhere('description', 'Radiofrecuencia')->employee_id);
        // La venta importada no crea paquetes por su cuenta: el del cliente llega aparte, una sola vez.
        $owned = CustomerPackage::sole();
        $this->assertSame($sale->id, $owned->sale_id);
        $this->assertSame(1, $owned->used_sessions);
        $this->assertSame(1, $owned->sessions()->count());

        $attendance = Attendance::sole();
        $this->assertSame(Appointment::sole()->id, $attendance->appointment_id);
        $this->assertSame(1, $attendance->supplies()->count());
        $this->assertSame($attendance->id, Commission::sole()->attendance_id);

        $session = CashSession::sole();
        $this->assertEquals(500.0, (float) $session->cash_sales);
        $this->assertEquals(10.0, (float) $session->expense);
        $this->assertSame('closed', $session->status);

        $this->assertEquals(5.0, $this->stockOf($gel), 'El historial no mueve stock.');
        $this->assertSame(1, InventoryMovement::where('product_id', $gel->id)->count());

        // Repetir la importación no duplica nada.
        $this->import('customers', [['id' => 3, 'full_name' => 'Oscar Echegaray']])->assertJsonPath('data.existing', 1);
        $this->import('sales', [['id' => 1, 'code' => 'V-000001', 'items' => []]])->assertJsonPath('data.existing', 1);
        $this->import('packages', [['id' => 2, 'name' => 'x', 'price' => 1, 'total_sessions' => 1]])->assertJsonPath('data.links.2', $packageProductId);
        $this->assertSame(1, Customer::count());
        $this->assertSame(1, Sale::count());
        $this->assertSame(1, Package::count());
    }

    public function test_un_cliente_ya_enlazado_desde_la_tienda_recibe_su_ficha_completa(): void
    {
        $id = $this->api('POST', 'customers', ['web_id' => 3, 'name' => 'Oscar Echegaray', 'document_number' => '72164814'])->json('data.id');

        $this->import('customers', [['id' => 3, 'full_name' => 'Oscar Echegaray', 'document_number' => '72164814',
            'district' => 'Miraflores', 'allergies' => 'Látex', 'birth_date' => '1990-05-01']])
            ->assertJsonPath('data.existing', 1)
            ->assertJsonPath('data.links.3', $id);

        $customer = Customer::findOrFail($id);
        $this->assertSame(1, Customer::count());
        $this->assertSame('Miraflores', $customer->district);
        $this->assertSame('Látex', $customer->allergies);
        $this->assertSame('1990-05-01', $customer->birth_date->toDateString());
    }

    public function test_importar_algo_cuyo_cliente_no_llego_falla_sin_dejar_nada_a_medias(): void
    {
        $facial = Product::factory()->service()->create();

        $this->api('POST', 'import/appointments', ['records' => [[
            'id' => 1, 'client_id' => 99, 'service_id' => $facial->id, 'employee_id' => 1,
            'appointment_date' => '2026-09-01', 'start_time' => '10:00', 'end_time' => '11:00', 'status' => 'pending',
        ]]])->assertStatus(422);

        $this->assertSame(0, Appointment::count());
    }
}
