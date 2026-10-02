<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use App\Core\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Agenda\Models\Appointment;
use Modules\Attendances\Models\Attendance;
use Modules\Cashbox\Models\CashRegister;
use Modules\Cashbox\Models\CashSession;
use Modules\Catalog\Models\Product;
use Modules\Commissions\Models\Commission;
use Modules\Commissions\Models\CommissionRule;
use Modules\Packages\Models\CustomerPackage;
use Modules\Packages\Models\CustomerPackageSession;
use Modules\Packages\Models\Package;
use Modules\Packages\Services\PackageService;
use Modules\Sales\Models\Sale;
use Modules\Staff\Models\Employee;
use Spatie\Permission\Models\Role;

/**
 * Trae al sistema el historial de la web (todo menos su contenido): usuarios,
 * personal, clientes, paquetes, ventas, citas, atenciones, comisiones y caja.
 *
 * Cada registro queda enlazado con el suyo (`web:{tipo}:{id}`): importar otra
 * vez no duplica nada y cada tipo encuentra lo importado antes. El orden
 * importa (lo hace la web): usuarios → personal → clientes → reglas →
 * paquetes → ventas → paquetes de clientes → citas → atenciones →
 * comisiones → caja. Los pedidos online van por su propia ruta.
 *
 * Nada de esto mueve stock: el stock que la web tenía al conectarse ya entró
 * con todo lo ocurrido descontado.
 */
class WebImporter
{
    public const KINDS = [
        'users', 'employees', 'customers', 'commission_rules', 'packages', 'sales',
        'customer_packages', 'appointments', 'attendances', 'commissions', 'cash_sessions',
    ];

    /** Medios de pago de la web → los del sistema. */
    private const METHODS = ['cash' => 'efectivo', 'transfer' => 'transferencia', 'pos' => 'tarjeta', 'card' => 'tarjeta'];

    public function __construct(
        private readonly WebLinks $links,
        private readonly CustomerResolver $customers,
        private readonly PackageService $packages,
        private readonly HistoricalSaleWriter $sales,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array{links: array<int|string, int>, created: int, existing: int}
     */
    public function import(string $kind, array $records): array
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new BusinessException("Tipo de importación desconocido: {$kind}.");
        }

        $type = Str::singular($kind);
        $result = ['links' => [], 'created' => 0, 'existing' => 0];

        foreach ($records as $record) {
            $webId = $record['id'];

            DB::transaction(function () use ($kind, $type, $record, $webId, &$result): void {
                $existing = $this->links->id($type, $webId);

                if ($existing !== null) {
                    $result['existing']++;
                    $result['links'][$webId] = $this->publicId($type, $existing);

                    return;
                }

                $model = match ($kind) {
                    'users' => $this->user($record),
                    'employees' => $this->employee($record),
                    'customers' => $this->customers->resolve($this->customerData($record), update: true)
                        ?? throw new BusinessException("El cliente {$webId} no tiene nombre."),
                    'commission_rules' => $this->commissionRule($record),
                    'packages' => $this->package($record),
                    'sales' => $this->sale($record),
                    'customer_packages' => $this->customerPackage($record),
                    'appointments' => $this->appointment($record),
                    'attendances' => $this->attendance($record),
                    'commissions' => $this->commission($record),
                    'cash_sessions' => $this->cashSession($record),
                };

                $this->links->link($type, $webId, $model);
                $result['created']++;
                $result['links'][$webId] = $this->publicId($type, (int) $model->getKey());
            });
        }

        return $result;
    }

    /** Lo que la web guarda como enlace: de un paquete, el id de su producto (el de su catálogo). */
    private function publicId(string $type, int $localId): int
    {
        return $type === 'package' ? (int) Package::withTrashed()->whereKey($localId)->value('product_id') : $localId;
    }

    // ------------------------------------------------------------------ Tipos

    /** @param  array<string, mixed>  $r */
    private function user(array $r): User
    {
        $user = User::withTrashed()->where('email', $r['email'])->first();

        if (! $user) {
            $user = User::create([
                'company_id' => \Modules\Settings\Models\Company::query()->value('id'),
                'name' => $r['name'],
                'email' => $r['email'],
                // Contraseña provisional: abajo se copia el hash de la web para que entren con la misma.
                'password' => Str::random(40),
                'is_active' => (bool) ($r['active'] ?? true),
            ]);

            if (! empty($r['password_hash'])) {
                DB::table('users')->where('id', $user->id)->update(['password' => $r['password_hash']]);
            }

            $roles = array_values(array_filter((array) ($r['roles'] ?? []), fn ($name) => Role::where('name', $name)->where('guard_name', 'web')->exists()));
            if ($roles !== []) {
                $user->syncRoles($roles);
            }
        }

        return $user;
    }

    /** @param  array<string, mixed>  $r */
    private function employee(array $r): Employee
    {
        $employee = Employee::create([
            'user_id' => $this->links->id('user', $r['user_id'] ?? null),
            'name' => $r['name'],
            'position' => $r['position'] ?? null,
            'phone' => $r['phone'] ?? null,
            'doc_number' => $r['document_number'] ?? null,
            'is_active' => (bool) ($r['active'] ?? true),
        ]);
        $employee->services()->sync($this->existingProducts($r['service_ids'] ?? []));

        return $employee;
    }

    /** @param  array<string, mixed>  $r */
    private function customerData(array $r): array
    {
        return [
            'web_id' => $r['id'],
            'code' => $r['code'] ?? null,
            'name' => $r['full_name'] ?? $r['name'],
            'document_number' => $r['document_number'] ?? null,
            'email' => $r['email'] ?? null,
            'phone' => $r['phone'] ?? null,
            'whatsapp' => $r['whatsapp'] ?? null,
            'address' => $r['address'] ?? null,
            'district' => $r['district'] ?? null,
            'birth_date' => $r['birth_date'] ?? null,
            'gender' => $r['gender'] ?? null,
            'how_knew' => $r['how_knew'] ?? null,
            'notes' => $r['observations'] ?? null,
            'allergies' => $r['allergies'] ?? null,
            'restrictions' => $r['restrictions'] ?? null,
            'contraindications' => $r['contraindications'] ?? null,
            'medications' => $r['medications'] ?? null,
            'relevant_info' => $r['relevant_info'] ?? null,
            'active' => $r['active'] ?? true,
        ];
    }

    /** @param  array<string, mixed>  $r */
    private function commissionRule(array $r): CommissionRule
    {
        $employeeId = $this->links->id('employee', $r['employee_id'] ?? null);
        $serviceId = $this->existingProducts([$r['service_id'] ?? null])[0] ?? null;

        // Una regla igual ya existente se reutiliza (la combinación es única).
        return CommissionRule::firstOrCreate(
            ['employee_id' => $employeeId, 'service_id' => $serviceId],
            ['type' => $r['type'], 'value' => $r['value'], 'is_active' => (bool) ($r['active'] ?? true)],
        );
    }

    /** @param  array<string, mixed>  $r */
    private function package(array $r): Package
    {
        /** @var Package $package */
        $package = $this->packages->create([
            'name' => $r['name'],
            'description' => $r['description'] ?? null,
            'price' => $r['price'],
            'total_sessions' => $r['total_sessions'],
            'validity_days' => $r['validity_days'] ?? null,
            'is_active' => (bool) ($r['active'] ?? true),
            'service_ids' => $this->existingProducts($r['service_ids'] ?? []),
        ]);

        // Con el código WEB-K-{id}, el aviso de catálogo que llega a la web
        // encuentra su fila original aunque todavía no esté enlazada.
        $package->product?->update(['code' => 'WEB-K-'.$r['id']]);

        return $package;
    }

    /** @param  array<string, mixed>  $r */
    private function sale(array $r): Sale
    {
        $items = array_map(function (array $i): array {
            $productId = ($i['type'] ?? null) === 'package'
                ? Package::withTrashed()->whereKey($this->links->id('package', $i['package_id'] ?? null))->value('product_id')
                : ($this->existingProducts([$i['product_id'] ?? null])[0] ?? null);

            return [
                'product_id' => $productId,
                'employee_id' => $this->links->id('employee', $i['employee_id'] ?? null),
                'description' => $i['description'],
                'quantity' => (float) $i['quantity'],
                'price' => (float) $i['unit_price'],
                'discount' => (float) ($i['discount'] ?? 0),
                'subtotal' => (float) $i['subtotal'],
            ];
        }, $r['items'] ?? []);

        return $this->sales->write([
            'reference' => $r['code'],
            'channel' => Sale::CHANNEL_WEB_PANEL,
            'customer_id' => $this->links->id('customer', $r['client_id'] ?? null),
            'user_id' => $this->links->id('user', $r['created_by'] ?? null),
            'sold_at' => $r['created_at'] ?? now(),
            'notes' => $r['notes'] ?? null,
            'discount' => (float) ($r['discount'] ?? 0),
            'status' => ($r['status'] ?? null) === 'cancelled' ? 'cancelled' : 'completed',
            'items' => $items,
            'payments' => array_map(fn (array $p) => [
                'method' => $this->method($p['method'] ?? 'cash'),
                'amount' => (float) $p['amount'],
                'reference' => $p['reference'] ?? null,
                'user_id' => $this->links->id('user', $p['created_by'] ?? null),
                'paid_at' => $p['paid_at'] ?? null,
            ], $r['payments'] ?? []),
        ]);
    }

    /** @param  array<string, mixed>  $r */
    private function customerPackage(array $r): CustomerPackage
    {
        $customerId = $this->links->id('customer', $r['client_id'] ?? null)
            ?? throw new BusinessException("El paquete de cliente {$r['id']} es de un cliente que no se importó.");

        return CustomerPackage::create([
            'customer_id' => $customerId,
            // Nacido en la web: por su enlace. Venido del sistema: por su producto.
            'package_id' => $this->links->id('package', $r['package_id'] ?? null)
                ?? (! empty($r['package_product_id']) ? Package::withTrashed()->where('product_id', $r['package_product_id'])->value('id') : null),
            'sale_id' => $this->links->id('sale', $r['sale_id'] ?? null),
            'package_name' => $r['package_name'],
            'price' => $r['price'],
            'total_sessions' => $r['total_sessions'],
            'used_sessions' => $r['used_sessions'] ?? 0,
            'purchased_at' => $r['purchased_at'],
            'expires_at' => $r['expires_at'] ?? null,
            'status' => in_array($r['status'] ?? '', CustomerPackage::STATUSES, true) ? $r['status'] : CustomerPackage::STATUS_ACTIVE,
        ]);
    }

    /** @param  array<string, mixed>  $r */
    private function appointment(array $r): Appointment
    {
        return Appointment::create([
            'customer_id' => $this->required('customer', $r['client_id'] ?? null, "cita {$r['id']}"),
            'service_id' => $this->existingProducts([$r['service_id'] ?? null])[0] ?? throw new BusinessException("La cita {$r['id']} es de un servicio que no está en el sistema."),
            'employee_id' => $this->required('employee', $r['employee_id'] ?? null, "cita {$r['id']}"),
            'appointment_date' => $r['appointment_date'],
            'start_time' => substr((string) $r['start_time'], 0, 5).':00',
            'end_time' => substr((string) $r['end_time'], 0, 5).':00',
            'status' => in_array($r['status'] ?? '', Appointment::STATUSES, true) ? $r['status'] : Appointment::STATUS_PENDING,
            'notes' => $r['notes'] ?? null,
        ]);
    }

    /**
     * Atención histórica: sus insumos se registran sin mover stock y, si fue
     * una sesión de paquete, se anota esa sesión (el saldo del paquete ya
     * llegó con su paquete).
     *
     * @param  array<string, mixed>  $r
     */
    private function attendance(array $r): Attendance
    {
        $customerPackageId = $this->links->id('customer_package', $r['client_package_id'] ?? null);

        $attendance = Attendance::create([
            'customer_id' => $this->required('customer', $r['client_id'] ?? null, "atención {$r['id']}"),
            'service_id' => $this->existingProducts([$r['service_id'] ?? null])[0] ?? throw new BusinessException("La atención {$r['id']} es de un servicio que no está en el sistema."),
            'employee_id' => $this->required('employee', $r['employee_id'] ?? null, "atención {$r['id']}"),
            'appointment_id' => $this->links->id('appointment', $r['appointment_id'] ?? null),
            'customer_package_id' => $customerPackageId,
            'session_number' => $r['session_number'] ?? null,
            'attended_at' => $r['attended_at'],
            'observations' => $r['observations'] ?? null,
            'measurements' => $r['measurements'] ?? null,
            'created_by' => $this->links->id('user', $r['created_by'] ?? null),
        ]);
        $attendance->forceFill(['created_at' => Carbon::parse($r['created_at'] ?? $r['attended_at'])])->saveQuietly();

        $supplies = collect($r['supplies'] ?? [])
            ->map(fn (array $s) => ['product_id' => $this->existingProducts([$s['product_id'] ?? null])[0] ?? null, 'quantity' => (float) $s['quantity']])
            ->filter(fn (array $s) => $s['product_id'] !== null && $s['quantity'] > 0)
            ->unique('product_id');
        foreach ($supplies as $supply) {
            $attendance->supplies()->create($supply);
        }

        if ($customerPackageId && ! empty($r['session_number'])) {
            CustomerPackageSession::firstOrCreate(
                ['customer_package_id' => $customerPackageId, 'session_number' => (int) $r['session_number']],
                ['attendance_id' => $attendance->id, 'consumed_at' => Carbon::parse($r['created_at'] ?? $r['attended_at']), 'user_id' => $attendance->created_by],
            );
        }

        return $attendance;
    }

    /** @param  array<string, mixed>  $r */
    private function commission(array $r): Commission
    {
        $attendanceId = $this->links->id('attendance', $r['attendance_id'] ?? null);

        $attributes = [
            'employee_id' => $this->required('employee', $r['employee_id'] ?? null, "comisión {$r['id']}"),
            'sale_id' => $this->links->id('sale', $r['sale_id'] ?? null),
            'service_id' => $this->existingProducts([$r['service_id'] ?? null])[0] ?? null,
            'base_amount' => $r['base_amount'],
            'type' => $r['type'],
            'value' => $r['value'],
            'amount' => $r['amount'],
            'status' => ($r['status'] ?? '') === Commission::STATUS_PAID ? Commission::STATUS_PAID : Commission::STATUS_PENDING,
            'generated_at' => $r['generated_at'],
            'paid_at' => $r['paid_at'] ?? null,
            'paid_by' => $this->links->id('user', $r['paid_by'] ?? null),
        ];

        return $attendanceId
            ? Commission::firstOrCreate(['attendance_id' => $attendanceId], $attributes)
            : Commission::create($attributes);
    }

    /**
     * Turno de caja de la web, con su arqueo. Los cobros en efectivo del turno
     * llegan como total (las ventas ya traen sus pagos); los egresos, como
     * movimientos. Un turno que sigue abierto se puede cerrar aquí, salvo que
     * su cajero ya tenga otro abierto.
     *
     * @param  array<string, mixed>  $r
     */
    private function cashSession(array $r): CashSession
    {
        $userId = $this->required('user', $r['opened_by'] ?? null, "caja {$r['id']}");
        $movements = collect($r['movements'] ?? []);

        $cashSales = $movements->where('type', 'sale')->filter(fn ($m) => ($m['payment_method'] ?? 'cash') === 'cash' || ($m['payment_method'] ?? null) === null)->sum('amount');
        $income = $movements->where('type', 'adjustment')->filter(fn ($m) => (float) $m['amount'] > 0)->sum('amount');
        $expense = abs($movements->whereIn('type', ['expense', 'adjustment'])->filter(fn ($m) => (float) $m['amount'] < 0)->sum('amount'));

        $open = ($r['status'] ?? 'closed') === 'open'
            && ! CashSession::where('user_id', $userId)->where('status', 'open')->exists();

        $session = CashSession::create([
            'cash_register_id' => CashRegister::firstOrCreate(['name' => 'Caja Principal'], ['is_active' => true])->id,
            'user_id' => $userId,
            'opening_amount' => $r['opening_amount'] ?? 0,
            'cash_sales' => $open ? 0 : round((float) $cashSales, 2),
            'income' => round((float) $income, 2),
            'expense' => round((float) $expense, 2),
            'expected_amount' => $open ? null : ($r['expected_amount'] ?? null),
            'counted_amount' => $open ? null : ($r['counted_amount'] ?? null),
            'difference' => $open ? null : ($r['difference'] ?? null),
            'status' => $open ? 'open' : 'closed',
            'notes' => trim(($r['notes'] ?? '').(($r['status'] ?? '') === 'open' && ! $open ? ' (abierta en la web al migrar)' : '')) ?: null,
            'opened_at' => $r['opened_at'],
            'closed_at' => $open ? null : ($r['closed_at'] ?? $r['opened_at']),
        ]);

        foreach ($movements->whereIn('type', ['expense', 'adjustment']) as $m) {
            $session->movements()->create([
                'user_id' => $this->links->id('user', $m['created_by'] ?? null),
                'type' => (float) $m['amount'] < 0 ? 'expense' : 'income',
                'amount' => abs((float) $m['amount']),
                'reason' => Str::limit((string) ($m['description'] ?? 'Movimiento de la web'), 250),
            ])->forceFill(['created_at' => Carbon::parse($m['created_at'] ?? $r['opened_at'])])->saveQuietly();
        }

        return $session;
    }

    // ------------------------------------------------------------- Utilidades

    /**
     * Ids de productos del sistema que existen (la web manda los ids de aquí,
     * su `erp_id`; uno que no exista se descarta).
     *
     * @param  list<int|string|null>  $ids
     * @return list<int>
     */
    private function existingProducts(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', array_filter($ids, fn ($v) => $v !== null && $v !== ''))));

        return $ids === [] ? [] : Product::withTrashed()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    private function required(string $type, int|string|null $webId, string $what): int
    {
        return $this->links->id($type, $webId)
            ?? throw new BusinessException("Falta importar el {$type} {$webId} de la {$what}.");
    }

    private function method(string $code): string
    {
        $code = strtolower(trim($code));

        return Str::limit(self::METHODS[$code] ?? $code, 20, '');
    }
}
