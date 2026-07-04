<?php

declare(strict_types=1);

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Empresa (multiempresa-ready). */
class Company extends Model implements Auditable
{
    use SoftDeletes;
    use AuditableTrait;

    protected $fillable = [
        'business_name', 'trade_name', 'ruc', 'address', 'phone', 'email',
        'website', 'logo_path', 'currency', 'currency_symbol', 'igv_percent',
        'prices_include_igv', 'is_active',
    ];

    protected $casts = [
        'igv_percent' => 'decimal:2',
        'prices_include_igv' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
