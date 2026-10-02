<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Catalog\Models\Product;
use Modules\Contacts\Models\Customer;
use Modules\Integration\Models\IntegrationReference;
use Modules\Sales\Models\DocumentSeries;
use Modules\Sales\Models\Sale;
use Modules\Settings\Models\Branch;
use Modules\Settings\Models\Company;

/**
 * Trae del ERP anterior (voroz, la versión previa de este mismo sistema) sus
 * clientes y sus ventas, a partir del volcado SQL de su base.
 *
 * - Clientes: se enlazan con los que ya están aquí (por DNI/RUC o correo) o se
 *   crean; de la ficha existente solo se completan los datos vacíos.
 * - Ventas: entran tal como fueron (fecha, comprobante, anulación, pagos) y
 *   SIN mover stock: son historia. Sus líneas se enlazan con los productos de
 *   aquí cuando se reconoce el producto (ver PRODUCTS).
 * - La numeración T001/B001 sigue desde la última venta importada, para que
 *   el POS no repita comprobantes.
 *
 * Se puede repetir: cada venta se reconoce por su referencia `VOROZ-…`.
 */
class VorozImporter
{
    /**
     * Productos de voroz (nombre en mayúsculas) → código del producto aquí.
     * Son los mismos suplementos que llegaron de la web, con otro nombre.
     */
    public const PRODUCTS = [
        'BPL 1' => 'WEB-P-22',
        'BPL1 SLIMFLORA' => 'WEB-P-22',
        'OMEGA 3 - 2500 MG - 120 SFG' => 'WEB-P-17',
        'ACIDO ALFA LIPOICO 600 MG - 240 CAPS' => 'WEB-P-18',
        'ENZIMAS DIGESTIVAS 620 MG - 240 CAPS' => 'WEB-P-19',
        'INOSITOL 500 MG - 240 CAPS' => 'WEB-P-20',
        'POTASSIUM CITRATE 99 MG + MAGNESIUM CITRATE 210 MG - 240 CAPS' => 'WEB-P-21',
    ];

    public const REFERENCE_PREFIX = 'VOROZ-';

    /** @var array<int, int|null> producto de voroz → producto de aquí */
    private array $productMap = [];

    /** @var array<string, string> nombre en voroz → producto de aquí (para el reporte) */
    private array $productReport = [];

    /**
     * @return array{
     *     customers: array{created:int, linked:int},
     *     sales: array{imported:int, skipped:int, completed:int, cancelled:int, total:float},
     *     products: array<string, string>,
     *     renumbered: list<string>,
     *     series: array<string, int>
     * }
     */
    public function import(SqlDumpReader $dump): array
    {
        return DB::transaction(function () use ($dump): array {
            $customers = $this->customers($dump->rows('customers'));
            $this->mapProducts($dump->rows('products'));
            $sales = $this->sales($dump, $customers['map']);
            $series = $this->continueNumbering($dump->rows('sales'));

            return [
                'customers' => ['created' => $customers['created'], 'linked' => $customers['linked']],
                'sales' => $sales['summary'],
                'products' => $this->productReport,
                'renumbered' => $sales['renumbered'],
                'series' => $series,
            ];
        });
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return array{map: array<int, int>, created: int, linked: int}
     */
    private function customers(array $rows): array
    {
        $map = [];
        $created = 0;
        $linked = 0;
        $companyId = Company::query()->value('id');

        foreach ($rows as $row) {
            if ($row['deleted_at'] !== null) {
                continue;
            }

            $doc = trim((string) $row['doc_number']);
            $email = trim((string) $row['email']) ?: null;
            // Enlace de una importación anterior: reconoce también a quien no tiene documento ni correo.
            $reference = 'voroz:customer:'.$row['id'];
            $linkedId = IntegrationReference::where('reference', $reference)->value('model_id');

            $customer = ($linkedId ? Customer::withTrashed()->find($linkedId) : null)
                ?? ($doc !== '' ? Customer::withTrashed()->where('doc_number', $doc)->first() : null)
                ?? ($email ? Customer::withTrashed()->where('email', $email)->first() : null);

            if ($customer) {
                // Solo se completa lo que la ficha de aquí no tiene.
                foreach (['email' => $email, 'phone' => $row['phone'], 'address' => $row['address']] as $field => $value) {
                    if (blank($customer->{$field}) && filled($value)) {
                        $customer->{$field} = $value;
                    }
                }
                $customer->save();
                $linked++;
            } else {
                $customer = Customer::create([
                    'company_id' => $companyId,
                    'doc_type' => $row['doc_type'] ?: 'DNI',
                    'doc_number' => $doc !== '' ? $doc : null,
                    'name' => $row['name'],
                    'email' => $email,
                    'phone' => $row['phone'],
                    'address' => $row['address'],
                    'notes' => trim(($row['notes'] ? $row['notes'].' · ' : '').'Cliente del ERP anterior (voroz)'),
                    'is_active' => (bool) $row['is_active'],
                ]);
                $customer->forceFill(['created_at' => $row['created_at']])->saveQuietly();
                $created++;
            }

            IntegrationReference::updateOrCreate(
                ['reference' => $reference],
                ['kind' => IntegrationReference::KIND_LINK, 'model_type' => Customer::class, 'model_id' => $customer->id],
            );
            $map[(int) $row['id']] = $customer->id;
        }

        return ['map' => $map, 'created' => $created, 'linked' => $linked];
    }

    /** @param  list<array<string, string|null>>  $rows */
    private function mapProducts(array $rows): void
    {
        foreach ($rows as $row) {
            $name = mb_strtoupper(trim((string) $row['name']));
            $code = self::PRODUCTS[$name] ?? null;

            $product = ($code ? Product::withTrashed()->where('code', $code)->first() : null)
                ?? Product::withTrashed()->whereRaw('UPPER(name) = ?', [$name])->first();

            $this->productMap[(int) $row['id']] = $product?->id;
            $this->productReport[$row['name']] = $product ? "{$product->name} ({$product->code})" : 'sin enlace (queda solo la descripción)';
        }
    }

    /**
     * @param  array<int, int>  $customers
     * @return array{summary: array{imported:int, skipped:int, completed:int, cancelled:int, total:float}, renumbered: list<string>}
     */
    private function sales(SqlDumpReader $dump, array $customers): array
    {
        $items = collect($dump->rows('sale_items'))->groupBy('sale_id');
        $payments = collect($dump->rows('sale_payments'))->groupBy('sale_id');
        $company = Company::query()->first();
        $summary = ['imported' => 0, 'skipped' => 0, 'completed' => 0, 'cancelled' => 0, 'total' => 0.0];
        $renumbered = [];

        foreach ($dump->rows('sales') as $row) {
            if ($row['deleted_at'] !== null || $row['status'] === 'quotation') {
                continue;
            }

            $reference = self::REFERENCE_PREFIX.$row['full_number'];
            if (Sale::withTrashed()->where('external_reference', $reference)->exists()) {
                $summary['skipped']++;

                continue;
            }

            // Si aquí ya se emitió ese mismo número, se distingue con el prefijo.
            $fullNumber = $row['full_number'];
            if (Sale::withTrashed()->where('full_number', $fullNumber)->exists()) {
                $fullNumber = Str::limit('VZ-'.$row['full_number'], 30, '');
                $renumbered[] = "{$row['full_number']} → {$fullNumber}";
            }

            $cancelled = $row['status'] === 'cancelled';
            $sale = Sale::create([
                'company_id' => $company?->id,
                'customer_id' => $row['customer_id'] !== null ? ($customers[(int) $row['customer_id']] ?? null) : null,
                'user_id' => null,
                'doc_type' => $row['doc_type'],
                'channel' => Sale::CHANNEL_POS,
                'external_reference' => $reference,
                'series' => $row['series'],
                'number' => $row['number'],
                'full_number' => $fullNumber,
                'subtotal' => $row['subtotal'],
                'tax' => $row['tax'],
                'discount' => $row['discount'],
                'total' => $row['total'],
                'tax_percent' => $row['tax_percent'],
                'paid' => $row['paid'],
                'change' => $row['change'],
                'status' => $row['status'],
                'payment_status' => $row['payment_status'],
                'notes' => trim(($row['notes'] ? $row['notes'].' · ' : '').'Venta del ERP anterior (voroz)'),
                'sold_at' => $row['sold_at'],
                'cancelled_at' => $row['cancelled_at'],
                'cancel_reason' => $row['cancel_reason'],
            ]);
            $sale->forceFill(['created_at' => $row['created_at'], 'updated_at' => $row['updated_at']])->saveQuietly();

            foreach ($items->get($row['id'], []) as $item) {
                $sale->items()->create([
                    'product_id' => $item['product_id'] !== null ? ($this->productMap[(int) $item['product_id']] ?? null) : null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'cost' => $item['cost'],
                    'discount' => $item['discount'],
                    'tax' => $item['tax'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            foreach ($payments->get($row['id'], []) as $payment) {
                $sale->payments()->create([
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'],
                    'paid_at' => Carbon::parse($payment['created_at'] ?? $row['sold_at']),
                ]);
            }

            $summary['imported']++;
            if ($cancelled) {
                $summary['cancelled']++;
            } else {
                $summary['completed']++;
                $summary['total'] += (float) $row['total'];
            }
        }

        $summary['total'] = round($summary['total'], 2);

        return ['summary' => $summary, 'renumbered' => $renumbered];
    }

    /**
     * El POS sigue la numeración desde la última venta de voroz (si es mayor
     * que la de aquí), en todas las sucursales.
     *
     * @param  list<array<string, string|null>>  $rows
     * @return array<string, int> serie => último número
     */
    private function continueNumbering(array $rows): array
    {
        $last = [];
        foreach ($rows as $row) {
            if ($row['series'] === null || $row['number'] === null) {
                continue;
            }
            $key = $row['doc_type'].'|'.$row['series'];
            $last[$key] = max($last[$key] ?? 0, (int) $row['number']);
        }

        $branches = Branch::query()->pluck('id')->all() ?: [null];
        $result = [];

        foreach ($last as $key => $number) {
            [$docType, $code] = explode('|', $key);

            foreach ($branches as $branchId) {
                $series = DocumentSeries::firstOrCreate(
                    ['branch_id' => $branchId, 'doc_type' => $docType, 'series' => $code],
                    ['current_number' => 0, 'is_active' => true],
                );
                if ($series->current_number < $number) {
                    $series->update(['current_number' => $number]);
                }
            }

            $result[$code] = $number;
        }

        return $result;
    }
}
