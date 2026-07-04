<?php

declare(strict_types=1);

namespace Modules\Contacts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Contacts\Database\Factories\SupplierFactory;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Proveedor. */
class Supplier extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use AuditableTrait;

    protected $fillable = [
        'company_id', 'doc_type', 'doc_number', 'name', 'contact_name',
        'email', 'phone', 'address', 'notes', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }
}
