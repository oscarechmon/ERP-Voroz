<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Contacts\Models\Customer;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Venta / comprobante (ticket, boleta, factura o cotización). */
class Sale extends Model implements Auditable
{
    use AuditableTrait;
    use SoftDeletes;

    /** Venta hecha en el POS del sistema. */
    public const CHANNEL_POS = 'pos';

    /** Pedido pagado en la tienda online de la web (llega por la integración). */
    public const CHANNEL_WEB = 'web';

    /** Venta hecha en el panel de la web antes de que todo pasara al sistema (histórico importado). */
    public const CHANNEL_WEB_PANEL = 'web_panel';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_PARTIAL = 'partial';

    public const PAYMENT_PENDING = 'pending';

    protected $fillable = [
        'company_id', 'branch_id', 'warehouse_id', 'customer_id', 'user_id',
        'doc_type', 'channel', 'external_reference', 'series', 'number', 'full_number',
        'subtotal', 'tax', 'discount', 'total', 'tax_percent', 'paid', 'change',
        'status', 'payment_status', 'notes', 'sold_at',
        'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'paid' => 'decimal:2',
        'change' => 'decimal:2',
        'sold_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Usuario que anuló la venta (si aplica). */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Ventas efectivas (no cotizaciones ni anuladas). */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /** Lo que falta cobrar. */
    public function balance(): float
    {
        if ($this->status !== 'completed') {
            return 0.0;
        }

        return round(max((float) $this->total - (float) $this->paid, 0), 2);
    }

    /** Estado de cobro para un total y lo ya pagado (regla única para checkout, cobros e importación). */
    public static function paymentStatusFor(float $total, float $paid): string
    {
        if ($paid + 0.001 >= $total) {
            return self::PAYMENT_PAID;
        }

        return $paid > 0 ? self::PAYMENT_PARTIAL : self::PAYMENT_PENDING;
    }
}
