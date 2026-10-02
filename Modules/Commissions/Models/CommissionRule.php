<?php

declare(strict_types=1);

namespace Modules\Commissions\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Product;
use Modules\Staff\Models\Employee;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Regla de comisión: porcentaje o monto fijo por empleado, servicio, ambos o general. */
class CommissionRule extends Model implements Auditable
{
    use AuditableTrait;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public const TYPES = [self::TYPE_PERCENTAGE, self::TYPE_FIXED];

    protected $fillable = ['employee_id', 'service_id', 'type', 'value', 'is_active'];

    protected $casts = [
        'value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'service_id')->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Monto de comisión para una base. */
    public static function calculate(string $type, float $base, float $value): float
    {
        return round($type === self::TYPE_PERCENTAGE ? $base * $value / 100 : $value, 2);
    }
}
