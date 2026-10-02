<?php

declare(strict_types=1);

namespace Modules\Commissions\Services;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Modules\Commissions\Models\Commission;
use Modules\Commissions\Models\CommissionRule;

/**
 * Resolución y cálculo de comisiones.
 *
 * La regla aplicable es la más específica:
 *   1. empleado + servicio
 *   2. empleado (cualquier servicio)
 *   3. servicio (cualquier empleado)
 *   4. regla general del centro
 */
class CommissionService
{
    public function resolveRule(int $employeeId, ?int $serviceId): ?CommissionRule
    {
        return CommissionRule::active()
            ->where(fn ($q) => $q->where('employee_id', $employeeId)->orWhereNull('employee_id'))
            ->where(fn ($q) => $serviceId
                ? $q->whereNull('service_id')->orWhere('service_id', $serviceId)
                : $q->whereNull('service_id'))
            ->get()
            ->sortByDesc(fn (CommissionRule $rule) => ($rule->employee_id !== null ? 2 : 0) + ($rule->service_id !== null ? 1 : 0))
            ->first();
    }

    /**
     * Comisión de una atención. Devuelve null si no hay regla: no todos los
     * servicios comisionan y eso no debe impedir registrar la atención.
     * Reintentar no la duplica (una por atención).
     */
    public function generateForAttendance(int $attendanceId, int $employeeId, ?int $serviceId, float $baseAmount, string $date): ?Commission
    {
        $rule = $this->resolveRule($employeeId, $serviceId);
        if (! $rule) {
            return null;
        }

        $amount = CommissionRule::calculate($rule->type, $baseAmount, (float) $rule->value);
        if ($amount <= 0) {
            return null;
        }

        return Commission::firstOrCreate(
            ['attendance_id' => $attendanceId],
            [
                'employee_id' => $employeeId,
                'service_id' => $serviceId,
                'base_amount' => round($baseAmount, 2),
                'type' => $rule->type,
                'value' => $rule->value,
                'amount' => $amount,
                'status' => Commission::STATUS_PENDING,
                'generated_at' => $date,
            ],
        );
    }

    /** Marca como pagadas las comisiones pendientes indicadas. */
    public function pay(array $ids, int $userId): int
    {
        if ($ids === []) {
            throw new BusinessException('Selecciona al menos una comisión.');
        }

        return DB::transaction(fn (): int => Commission::whereIn('id', $ids)
            ->where('status', Commission::STATUS_PENDING)
            ->update(['status' => Commission::STATUS_PAID, 'paid_at' => now(), 'paid_by' => $userId]));
    }
}
