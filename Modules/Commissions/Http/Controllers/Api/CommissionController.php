<?php

declare(strict_types=1);

namespace Modules\Commissions\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Commissions\Models\Commission;
use Modules\Commissions\Services\CommissionService;

/** Comisiones generadas: consulta con totales y pago. */
class CommissionController extends ApiController
{
    public function __construct(private readonly CommissionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'employee_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([Commission::STATUS_PENDING, Commission::STATUS_PAID])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer'],
        ]);

        $query = Commission::query()
            ->when($filters['employee_id'] ?? null, fn (Builder $q, $id) => $q->where('employee_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('generated_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('generated_at', '<=', $to));

        $totals = [
            'pending' => round((float) (clone $query)->where('status', Commission::STATUS_PENDING)->sum('amount'), 2),
            'paid' => round((float) (clone $query)->where('status', Commission::STATUS_PAID)->sum('amount'), 2),
        ];

        $page = $query->with(['employee:id,name', 'service:id,name', 'payer:id,name'])
            ->orderByDesc('generated_at')->orderByDesc('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 15), 1), 100));

        return $this->ok([
            'data' => $page->getCollection()->map(fn (Commission $c) => [
                'id' => $c->id,
                'employee' => $c->employee?->name,
                'employee_id' => $c->employee_id,
                'service' => $c->service?->name,
                'attendance_id' => $c->attendance_id,
                'base_amount' => (float) $c->base_amount,
                'type' => $c->type,
                'value' => (float) $c->value,
                'amount' => (float) $c->amount,
                'status' => $c->status,
                'generated_at' => $c->generated_at?->toDateString(),
                'paid_at' => $c->paid_at?->toIso8601String(),
                'paid_by' => $c->payer?->name,
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'totals' => $totals,
        ]);
    }

    public function pay(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];

        $count = $this->service->pay($ids, (int) auth()->id());

        return $this->ok(['paid' => $count], $count === 1 ? '1 comisión pagada.' : "{$count} comisiones pagadas.");
    }
}
