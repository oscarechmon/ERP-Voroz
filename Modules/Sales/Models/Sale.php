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
    use SoftDeletes;
    use AuditableTrait;

    protected $fillable = [
        'company_id', 'branch_id', 'warehouse_id', 'customer_id', 'user_id',
        'doc_type', 'series', 'number', 'full_number',
        'subtotal', 'tax', 'discount', 'total', 'tax_percent', 'paid', 'change',
        'status', 'payment_status', 'notes', 'sold_at',
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

    /** Ventas efectivas (no cotizaciones ni anuladas). */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
}
