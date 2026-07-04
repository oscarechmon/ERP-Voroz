<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Database\Factories\ProductFactory;
use Modules\Settings\Models\Company;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Producto del catálogo.
 *
 * Concentra identificación, precios, control de stock y estado. El stock real por
 * almacén vive en el módulo Inventario (relación `stocks`), calculado on-demand.
 */
class Product extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use AuditableTrait;

    protected $fillable = [
        'company_id', 'category_id', 'brand_id', 'unit_id',
        'code', 'barcode', 'sku', 'name', 'description', 'image_path', 'qr_path',
        'cost', 'price', 'wholesale_price', 'offer_price',
        'stock_min', 'stock_max', 'track_stock', 'has_expiry', 'is_active',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'offer_price' => 'decimal:2',
        'stock_min' => 'decimal:2',
        'stock_max' => 'decimal:2',
        'track_stock' => 'boolean',
        'has_expiry' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['image_url', 'profit_margin', 'current_stock'];

    // --- Relaciones -------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    /** Existencias por almacén (módulo Inventario). */
    public function stocks(): HasMany
    {
        return $this->hasMany(\Modules\Inventory\Models\Stock::class);
    }

    // --- Accessors --------------------------------------------------------

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    /**
     * Stock total del producto sumando todos los almacenes. Usa `stocks_sum_quantity`
     * si el repositorio lo cargó con withSum (evita N+1); si no, lo calcula.
     */
    public function getCurrentStockAttribute(): float
    {
        if (array_key_exists('stocks_sum_quantity', $this->attributes)) {
            return (float) ($this->attributes['stocks_sum_quantity'] ?? 0);
        }

        if ($this->relationLoaded('stocks')) {
            return (float) $this->stocks->sum('quantity');
        }

        return (float) $this->stocks()->sum('quantity');
    }

    /** Margen de utilidad % sobre el precio de venta (0 si no hay precio). */
    public function getProfitMarginAttribute(): float
    {
        $price = (float) $this->price;
        if ($price <= 0) {
            return 0.0;
        }

        return round((($price - (float) $this->cost) / $price) * 100, 2);
    }

    // --- Scopes -----------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
