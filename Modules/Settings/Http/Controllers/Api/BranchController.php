<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Settings\Http\Requests\BranchRequest;
use Modules\Settings\Models\Branch;
use Modules\Settings\Models\Company;

/** Sucursales de la empresa. */
class BranchController extends ApiController
{
    public function index(): JsonResponse
    {
        $branches = Branch::withCount('warehouses')->orderByDesc('is_main')->orderBy('name')->get();

        return $this->ok($branches->map(fn (Branch $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'code' => $b->code,
            'address' => $b->address,
            'phone' => $b->phone,
            'is_main' => $b->is_main,
            'is_active' => $b->is_active,
            'warehouses_count' => $b->warehouses_count,
        ]));
    }

    public function store(BranchRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['company_id'] = Company::value('id');
        $branch = Branch::create($data);

        return $this->created($this->format($branch), 'Sucursal creada.');
    }

    public function update(BranchRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($request->validated());

        return $this->ok($this->format($branch), 'Sucursal actualizada.');
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $branch->delete();

        return $this->noContent('Sucursal eliminada.');
    }

    private function format(Branch $b): array
    {
        return [
            'id' => $b->id, 'name' => $b->name, 'code' => $b->code, 'address' => $b->address,
            'phone' => $b->phone, 'is_main' => $b->is_main, 'is_active' => $b->is_active,
        ];
    }
}
