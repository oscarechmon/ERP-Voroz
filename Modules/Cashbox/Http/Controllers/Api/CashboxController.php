<?php

declare(strict_types=1);

namespace Modules\Cashbox\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Cashbox\Http\Resources\CashSessionResource;
use Modules\Cashbox\Models\CashSession;
use Modules\Cashbox\Services\CashboxService;

class CashboxController extends ApiController
{
    public function __construct(private readonly CashboxService $service)
    {
    }

    /** Sesión de caja abierta del usuario actual (o null). */
    public function current(): JsonResponse
    {
        $session = $this->service->current((int) auth()->id());

        return $this->ok($session ? new CashSessionResource($session) : null);
    }

    public function open(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'cash_register_id' => ['nullable', 'exists:cash_registers,id'],
        ], [], ['opening_amount' => 'monto de apertura']);

        $session = $this->service->open((int) auth()->id(), $data['cash_register_id'] ?? null, (float) $data['opening_amount']);

        return $this->created(new CashSessionResource($session->load('register')), 'Caja abierta.');
    }

    public function movement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->service->movement((int) auth()->id(), $data['type'], (float) $data['amount'], $data['reason']);
        $session = $this->service->current((int) auth()->id());

        return $this->created(new CashSessionResource($session), 'Movimiento registrado.');
    }

    public function close(Request $request): JsonResponse
    {
        $data = $request->validate([
            'counted_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['counted_amount' => 'monto contado']);

        $session = $this->service->close((int) auth()->id(), (float) $data['counted_amount'], $data['notes'] ?? null);

        return $this->ok(new CashSessionResource($session), 'Caja cerrada. Arqueo registrado.');
    }

    /** Historial de sesiones cerradas. */
    public function history(Request $request): JsonResponse
    {
        $sessions = CashSession::with(['register', 'user'])
            ->where('status', 'closed')
            ->orderByDesc('closed_at')
            ->paginate(min((int) $request->integer('per_page', 15), 100));

        return $this->ok(CashSessionResource::collection($sessions)->response()->getData(true));
    }
}
