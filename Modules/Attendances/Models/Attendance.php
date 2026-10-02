<?php

declare(strict_types=1);

namespace Modules\Attendances\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Agenda\Models\Appointment;
use Modules\Catalog\Models\Product;
use Modules\Commissions\Models\Commission;
use Modules\Contacts\Models\Customer;
use Modules\Packages\Models\CustomerPackage;
use Modules\Staff\Models\Employee;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Atención realizada: servicio, cliente, especialista e insumos usados. */
class Attendance extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'customer_id', 'service_id', 'employee_id', 'appointment_id', 'customer_package_id',
        'session_number', 'attended_at', 'observations', 'measurements', 'created_by',
    ];

    protected $casts = [
        'attended_at' => 'date',
        'session_number' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'service_id')->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function customerPackage(): BelongsTo
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(AttendanceSupply::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
