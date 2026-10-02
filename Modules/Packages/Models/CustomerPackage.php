<?php

declare(strict_types=1);

namespace Modules\Packages\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contacts\Models\Customer;
use Modules\Sales\Models\Sale;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Paquete comprado por un cliente, con su saldo de sesiones. */
class CustomerPackage extends Model implements Auditable
{
    use AuditableTrait;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    /** Se anuló la venta que lo creó. */
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_EXPIRED, self::STATUS_CANCELLED];

    protected $fillable = [
        'customer_id', 'package_id', 'sale_id', 'package_name', 'price',
        'total_sessions', 'used_sessions', 'purchased_at', 'expires_at', 'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total_sessions' => 'integer',
        'used_sessions' => 'integer',
        'purchased_at' => 'date',
        'expires_at' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class)->withTrashed();
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CustomerPackageSession::class)->orderBy('session_number');
    }

    public function remainingSessions(): int
    {
        return max(0, $this->total_sessions - $this->used_sessions);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(today());
    }

    public function canConsumeSession(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired() && $this->remainingSessions() > 0;
    }

    /** Paquetes con sesiones disponibles hoy. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereColumn('used_sessions', '<', 'total_sessions')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', today()->toDateString()));
    }
}
