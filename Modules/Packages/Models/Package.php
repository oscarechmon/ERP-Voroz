<?php

declare(strict_types=1);

namespace Modules\Packages\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Catalog\Models\Product;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Paquete del catálogo: N sesiones de ciertos servicios, con vigencia opcional. */
class Package extends Model implements Auditable
{
    use AuditableTrait;
    use SoftDeletes;

    protected $fillable = ['product_id', 'name', 'description', 'price', 'total_sessions', 'validity_days', 'is_active', 'web_published'];

    protected $casts = [
        'price' => 'decimal:2',
        'total_sessions' => 'integer',
        'validity_days' => 'integer',
        'is_active' => 'boolean',
        'web_published' => 'boolean',
    ];

    /** Lo que vende el POS. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** Servicios incluidos. */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'package_services', 'package_id', 'product_id')->withTimestamps();
    }

    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
