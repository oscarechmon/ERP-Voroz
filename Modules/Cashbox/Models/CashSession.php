<?php

declare(strict_types=1);

namespace Modules\Cashbox\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Sesión de caja (turno). */
class CashSession extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'cash_register_id', 'user_id', 'opening_amount', 'cash_sales', 'income', 'expense',
        'expected_amount', 'counted_amount', 'difference', 'status', 'notes', 'opened_at', 'closed_at',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'income' => 'decimal:2',
        'expense' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'counted_amount' => 'decimal:2',
        'difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
