<?php

declare(strict_types=1);

namespace Modules\OnlineOrders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contacts\Models\Customer;
use Modules\Sales\Models\Sale;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Pedido de la tienda online. */
class OnlineOrder extends Model implements Auditable
{
    use AuditableTrait;

    public const PENDING_PAYMENT = 'pending_payment';

    public const PAYMENT_FAILED = 'payment_failed';

    public const PAID = 'paid';

    public const PREPARING = 'preparing';

    public const SHIPPED = 'shipped';

    public const READY_FOR_PICKUP = 'ready_for_pickup';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::PENDING_PAYMENT, self::PAYMENT_FAILED, self::PAID, self::PREPARING,
        self::SHIPPED, self::READY_FOR_PICKUP, self::DELIVERED, self::CANCELLED,
    ];

    /** Cobro confirmado: el pedido está en curso o entregado. */
    public const PAID_STATUSES = [self::PAID, self::PREPARING, self::SHIPPED, self::READY_FOR_PICKUP, self::DELIVERED];

    /**
     * Estados que fija la web (el cobro). Desde "pagado" en adelante, el
     * seguimiento es del sistema: lo que mande la web ya no lo pisa.
     */
    public const WEB_STATUSES = [self::PENDING_PAYMENT, self::PAYMENT_FAILED, self::PAID];

    /** Delivery en Lima, a la dirección del cliente. */
    public const DELIVERY = 'delivery';

    /** Envío a provincia por agencia (Shalom): se recoge en la agencia con DNI/CE. */
    public const PROVINCE = 'province';

    public const PICKUP = 'pickup';

    public const FULFILLMENTS = [self::DELIVERY, self::PROVINCE, self::PICKUP];

    /** Documentos con los que se recoge un envío a provincia. */
    public const DOCUMENT_TYPES = ['dni', 'ce'];

    protected $fillable = [
        'web_id', 'code', 'customer_id', 'sale_id', 'status', 'fulfillment',
        'customer_name', 'customer_email', 'recipient_name', 'document_type', 'document_number', 'phone',
        'address', 'district', 'reference', 'department', 'province', 'agency', 'notes',
        'subtotal', 'delivery_fee', 'total', 'gateway', 'payment_reference', 'paid_at', 'ordered_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'ordered_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OnlineOrderItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OnlineOrderStatusHistory::class)->orderBy('happened_at')->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public static function fulfillmentLabel(string $fulfillment): string
    {
        return match ($fulfillment) {
            self::DELIVERY => 'Delivery en Lima',
            self::PROVINCE => 'Envío a provincia (Shalom)',
            default => 'Recojo en el centro',
        };
    }

    /** Se envía (delivery o agencia) en lugar de recogerse en el centro. */
    public function isShipped(): bool
    {
        return in_array($this->fulfillment, [self::DELIVERY, self::PROVINCE], true);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, self::PAID_STATUSES, true);
    }

    /**
     * Estados a los que el personal puede mover el pedido. "Pagado" no está en
     * ninguna lista: solo lo fija la pasarela al confirmar el cobro.
     *
     * @return list<string>
     */
    public function nextStatuses(): array
    {
        return match ($this->status) {
            self::PENDING_PAYMENT, self::PAYMENT_FAILED => [self::CANCELLED],
            self::PAID => [self::PREPARING, self::CANCELLED],
            self::PREPARING => [$this->isShipped() ? self::SHIPPED : self::READY_FOR_PICKUP, self::CANCELLED],
            self::SHIPPED, self::READY_FOR_PICKUP => [self::DELIVERED],
            default => [],
        };
    }
}
