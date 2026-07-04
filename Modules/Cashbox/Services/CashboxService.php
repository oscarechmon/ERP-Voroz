<?php

declare(strict_types=1);

namespace Modules\Cashbox\Services;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Modules\Cashbox\Models\CashMovement;
use Modules\Cashbox\Models\CashRegister;
use Modules\Cashbox\Models\CashSession;
use Modules\Sales\Models\SalePayment;

/**
 * Lógica de caja: apertura de turno, registro de ingresos/egresos y cierre con
 * arqueo (calcula el monto esperado incluyendo las ventas en efectivo del turno
 * y la diferencia contra lo contado).
 */
class CashboxService
{
    /** Devuelve la sesión abierta del usuario actual, o null. */
    public function current(int $userId): ?CashSession
    {
        return CashSession::with(['register', 'movements' => fn ($q) => $q->latest()])
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /** Abre una sesión de caja. Impide dos sesiones abiertas por el mismo usuario. */
    public function open(int $userId, ?int $registerId, float $openingAmount): CashSession
    {
        if ($this->current($userId)) {
            throw new BusinessException('Ya tienes una caja abierta. Ciérrala antes de abrir otra.');
        }

        $register = $registerId
            ? CashRegister::findOrFail($registerId)
            : CashRegister::firstOrCreate(['name' => 'Caja Principal'], ['is_active' => true]);

        return CashSession::create([
            'cash_register_id' => $register->id,
            'user_id' => $userId,
            'opening_amount' => $openingAmount,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    /** Registra un ingreso o egreso manual en la sesión abierta. */
    public function movement(int $userId, string $type, float $amount, string $reason): CashMovement
    {
        $session = $this->requireOpen($userId);

        if ($amount <= 0) {
            throw new BusinessException('El monto debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($session, $userId, $type, $amount, $reason): CashMovement {
            $movement = $session->movements()->create([
                'user_id' => $userId,
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
            ]);

            $column = $type === 'income' ? 'income' : 'expense';
            $session->increment($column, $amount);

            return $movement;
        });
    }

    /** Cierra la sesión, calcula el esperado (incluye ventas en efectivo) y la diferencia. */
    public function close(int $userId, float $countedAmount, ?string $notes = null): CashSession
    {
        $session = $this->requireOpen($userId);

        return DB::transaction(function () use ($session, $countedAmount, $notes): CashSession {
            // Ventas en efectivo del turno (pagos método efectivo de las ventas del usuario).
            $cashSales = (float) SalePayment::query()
                ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                ->where('sale_payments.method', 'efectivo')
                ->where('sales.user_id', $session->user_id)
                ->where('sales.status', 'completed')
                ->where('sales.sold_at', '>=', $session->opened_at)
                ->sum('sale_payments.amount');

            $expected = round(
                (float) $session->opening_amount + $cashSales + (float) $session->income - (float) $session->expense,
                2,
            );

            $session->update([
                'cash_sales' => round($cashSales, 2),
                'expected_amount' => $expected,
                'counted_amount' => round($countedAmount, 2),
                'difference' => round($countedAmount - $expected, 2),
                'status' => 'closed',
                'notes' => $notes,
                'closed_at' => now(),
            ]);

            return $session->fresh(['register', 'movements']);
        });
    }

    private function requireOpen(int $userId): CashSession
    {
        $session = $this->current($userId);
        if (! $session) {
            throw new BusinessException('No tienes una caja abierta.');
        }

        return $session;
    }
}
