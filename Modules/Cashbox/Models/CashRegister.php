<?php

declare(strict_types=1);

namespace Modules\Cashbox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Caja física/lógica. */
class CashRegister extends Model
{
    protected $fillable = ['branch_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }
}
