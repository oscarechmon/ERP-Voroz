<?php

declare(strict_types=1);

namespace Modules\Cashbox\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Movimiento manual de caja. */
class CashMovement extends Model
{
    protected $fillable = ['cash_session_id', 'user_id', 'type', 'amount', 'reason'];

    protected $casts = ['amount' => 'decimal:2'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
