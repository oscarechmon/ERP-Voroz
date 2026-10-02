<?php

declare(strict_types=1);

namespace Modules\Packages\Services;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Packages\Models\CustomerPackage;
use Modules\Packages\Models\CustomerPackageSession;
use Modules\Packages\Models\Package;

/**
 * Saldo de sesiones de los paquetes de cada cliente.
 *
 * El saldo no se resta a ciegas: cada consumo crea una fila en
 * `customer_package_sessions` con el número de sesión, y su índice único impide
 * consumir dos veces la misma.
 */
class CustomerPackageService
{
    /**
     * Da un paquete a un cliente (al venderlo, o asignado a mano). Nombre,
     * precio y sesiones se copian del catálogo.
     */
    public function assign(Package $package, int $customerId, ?float $price = null, ?int $saleId = null, ?Carbon $purchasedAt = null): CustomerPackage
    {
        $purchasedAt ??= today();

        return CustomerPackage::create([
            'customer_id' => $customerId,
            'package_id' => $package->id,
            'sale_id' => $saleId,
            'package_name' => $package->name,
            'price' => round($price ?? (float) $package->price, 2),
            'total_sessions' => $package->total_sessions,
            'used_sessions' => 0,
            'purchased_at' => $purchasedAt->toDateString(),
            'expires_at' => $package->validity_days ? $purchasedAt->copy()->addDays($package->validity_days)->toDateString() : null,
            'status' => CustomerPackage::STATUS_ACTIVE,
        ]);
    }

    /** Consume la siguiente sesión del paquete. */
    public function consumeSession(CustomerPackage $customerPackage, ?int $attendanceId = null, ?int $userId = null): CustomerPackageSession
    {
        return DB::transaction(function () use ($customerPackage, $attendanceId, $userId): CustomerPackageSession {
            /** @var CustomerPackage $locked */
            $locked = CustomerPackage::whereKey($customerPackage->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === CustomerPackage::STATUS_ACTIVE && $locked->isExpired()) {
                $locked->update(['status' => CustomerPackage::STATUS_EXPIRED]);
            }

            if (! $locked->canConsumeSession()) {
                throw new BusinessException(match (true) {
                    $locked->status === CustomerPackage::STATUS_EXPIRED => "El paquete «{$locked->package_name}» está vencido.",
                    $locked->status === CustomerPackage::STATUS_CANCELLED => "El paquete «{$locked->package_name}» fue anulado.",
                    default => "El paquete «{$locked->package_name}» no tiene sesiones disponibles.",
                });
            }

            $number = $locked->used_sessions + 1;

            $session = $locked->sessions()->create([
                'attendance_id' => $attendanceId,
                'session_number' => $number,
                'consumed_at' => now(),
                'user_id' => $userId,
            ]);

            $locked->update([
                'used_sessions' => $number,
                'status' => $number >= $locked->total_sessions ? CustomerPackage::STATUS_COMPLETED : $locked->status,
            ]);

            $customerPackage->refresh();

            return $session;
        });
    }

    /** Devuelve una sesión al paquete (atención eliminada). */
    public function restoreSession(CustomerPackageSession $session): void
    {
        DB::transaction(function () use ($session): void {
            /** @var CustomerPackage $locked */
            $locked = CustomerPackage::whereKey($session->customer_package_id)->lockForUpdate()->firstOrFail();

            $session->delete();

            $locked->update([
                'used_sessions' => max(0, $locked->used_sessions - 1),
                'status' => $locked->status === CustomerPackage::STATUS_COMPLETED ? CustomerPackage::STATUS_ACTIVE : $locked->status,
            ]);
        });
    }

    /** Pasa a vencidos los paquetes activos cuya vigencia terminó. */
    public function expireOverdue(): int
    {
        return CustomerPackage::where('status', CustomerPackage::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', today()->toDateString())
            ->update(['status' => CustomerPackage::STATUS_EXPIRED]);
    }
}
