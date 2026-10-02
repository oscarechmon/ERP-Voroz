<?php

declare(strict_types=1);

namespace Modules\Commissions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Product;
use Modules\Staff\Models\Employee;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Comisión generada (normalmente por una atención), con su monto ya calculado. */
class Commission extends Model implements Auditable
{
    use AuditableTrait;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'employee_id', 'attendance_id', 'sale_id', 'service_id', 'base_amount',
        'type', 'value', 'amount', 'status', 'generated_at', 'paid_at', 'paid_by',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'value' => 'decimal:2',
        'amount' => 'decimal:2',
        'generated_at' => 'date',
        'paid_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'service_id')->withTrashed();
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
