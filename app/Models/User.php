<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Modules\Settings\Models\Branch;
use Modules\Settings\Models\Company;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modelo de Usuario del ERP.
 *
 * Combina: emisión de tokens (Sanctum), roles y permisos (Spatie), auditoría
 * automática de cambios (owen-it) y borrado lógico (SoftDeletes).
 */
class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasApiTokens;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;
    use AuditableTrait;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'email',
        'phone',
        'password',
        'avatar_path',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['avatar_url'];

    /** Atributos excluidos de la auditoría (evita registrar hashes/tokens). */
    protected array $auditExclude = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // --- Relaciones -------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // --- Accessors --------------------------------------------------------

    /** URL pública del avatar (o null). */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::url($this->avatar_path) : null;
    }
}
