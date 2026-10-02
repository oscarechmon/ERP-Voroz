<?php

declare(strict_types=1);

namespace Modules\Staff\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Catalog\Models\Product;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Empleado del centro (especialista, recepción…). */
class Employee extends Model implements Auditable
{
    use AuditableTrait;
    use SoftDeletes;

    protected $fillable = ['user_id', 'name', 'position', 'phone', 'doc_number', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Servicios que atiende. */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'employee_service', 'employee_id', 'product_id')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Quienes atienden un servicio (los que no tienen ninguno asignado atienden todos). */
    public function scopeForService(Builder $query, int $serviceId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereHas('services', fn (Builder $s) => $s->where('products.id', $serviceId))
            ->orWhereDoesntHave('services'));
    }
}
