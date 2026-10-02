<?php

declare(strict_types=1);

namespace Modules\Packages\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sesión consumida de un paquete (normalmente por una atención). */
class CustomerPackageSession extends Model
{
    protected $fillable = ['customer_package_id', 'attendance_id', 'session_number', 'consumed_at', 'user_id'];

    protected $casts = [
        'session_number' => 'integer',
        'consumed_at' => 'datetime',
    ];

    public function customerPackage(): BelongsTo
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
