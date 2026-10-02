<?php

declare(strict_types=1);

namespace Modules\Contacts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Contacts\Database\Factories\CustomerFactory;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Cliente, con su ficha del centro (datos personales y antecedentes). */
class Customer extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use AuditableTrait;

    public const GENDERS = ['M', 'F', 'O'];

    protected $fillable = [
        'company_id', 'code', 'doc_type', 'doc_number', 'name',
        'email', 'phone', 'whatsapp', 'address', 'district',
        'birth_date', 'gender', 'how_knew', 'notes',
        'allergies', 'restrictions', 'contraindications', 'medications', 'relevant_info',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'birth_date' => 'date',
    ];

    /** Todo cliente lleva código, venga del formulario, del POS o de la web. */
    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            if (empty($customer->code)) {
                $customer->code = self::nextCode();
            }
        });
    }

    /**
     * Siguiente código CLI-000123. Sigue al mayor existente (y no al id) para
     * no chocar con los clientes que llegaron de la web con su propio código.
     */
    public static function nextCode(): string
    {
        $last = self::withTrashed()->where('code', 'like', 'CLI-%')->orderByDesc('code')->value('code');
        $number = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'CLI-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
