<?php

declare(strict_types=1);

namespace Modules\Commissions\Http\Controllers\Api;

use App\Core\Exceptions\BusinessException;
use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Catalog\Models\Product;
use Modules\Commissions\Models\CommissionRule;

/** Reglas de comisión. */
class CommissionRuleController extends ApiController
{
    public function index(): JsonResponse
    {
        $rules = CommissionRule::with(['employee:id,name', 'service:id,name'])
            ->orderByRaw('employee_id is null')->orderByRaw('service_id is null')->orderBy('id')
            ->get();

        return $this->ok($rules->map(fn (CommissionRule $r) => $this->present($r))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->assertUnique($data);

        return $this->created($this->present(CommissionRule::create($data)->load('employee', 'service')), 'Regla creada.');
    }

    public function update(Request $request, int $rule): JsonResponse
    {
        $model = CommissionRule::findOrFail($rule);
        $data = $this->validated($request);
        $this->assertUnique($data, $model->id);
        $model->update($data);

        return $this->ok($this->present($model->load('employee', 'service')), 'Regla actualizada.');
    }

    public function destroy(int $rule): JsonResponse
    {
        CommissionRule::findOrFail($rule)->delete();

        return $this->noContent('Regla eliminada.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'service_id' => ['nullable', Rule::exists('products', 'id')->where('type', Product::TYPE_SERVICE)],
            'type' => ['required', Rule::in(CommissionRule::TYPES)],
            'value' => ['required', 'numeric', 'gt:0', Rule::when($request->input('type') === CommissionRule::TYPE_PERCENTAGE, ['max:100'])],
            'is_active' => ['boolean'],
        ], [], ['employee_id' => 'empleado', 'service_id' => 'servicio', 'type' => 'tipo', 'value' => 'valor']);

        return $data + ['employee_id' => null, 'service_id' => null, 'is_active' => true];
    }

    /** Una sola regla por combinación empleado/servicio (incluida la general). */
    private function assertUnique(array $data, ?int $ignoreId = null): void
    {
        $exists = CommissionRule::query()
            ->when($data['employee_id'], fn ($q, $id) => $q->where('employee_id', $id), fn ($q) => $q->whereNull('employee_id'))
            ->when($data['service_id'], fn ($q, $id) => $q->where('service_id', $id), fn ($q) => $q->whereNull('service_id'))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw new BusinessException('Ya hay una regla para esa combinación de empleado y servicio.');
        }
    }

    /** @return array<string, mixed> */
    private function present(CommissionRule $rule): array
    {
        return [
            'id' => $rule->id,
            'employee_id' => $rule->employee_id,
            'employee' => $rule->employee?->name,
            'service_id' => $rule->service_id,
            'service' => $rule->service?->name,
            'type' => $rule->type,
            'value' => (float) $rule->value,
            'is_active' => $rule->is_active,
        ];
    }
}
