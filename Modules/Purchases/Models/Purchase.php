<?php

declare(strict_types=1);

namespace Modules\Purchases\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Contacts\Models\Supplier;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Compra a proveedor. */
class Purchase extends Model implements Auditable
{
    use SoftDeletes;
    use AuditableTrait;

    protected $fillable = [
        'company_id', 'branch_id', 'warehouse_id', 'supplier_id', 'user_id',
        'number', 'supplier_doc', 'doc_type',
        'subtotal', 'tax', 'discount', 'total', 'tax_percent',
        'status', 'notes', 'purchased_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'purchased_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
