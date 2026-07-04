<?php

declare(strict_types=1);

namespace Modules\Users\Http\Controllers\Api;

use App\Core\Exceptions\BusinessException;
use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Users\Http\Requests\RoleRequest;
use Modules\Users\Http\Resources\RoleResource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends ApiController
{
    /** Lista de roles con su número de permisos y usuarios. */
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            return $this->ok(Role::orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]));
        }

        // Cuenta de usuarios por rol vía la tabla pivote (evita un quirk de Spatie
        // con withCount('users') al construir la relación sin guard_name).
        $userCounts = DB::table('model_has_roles')
            ->select('role_id', DB::raw('COUNT(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $roles = Role::with('permissions:id,name')->orderBy('name')->get()
            ->each(fn (Role $r) => $r->users_count = (int) ($userCounts[$r->id] ?? 0));

        return $this->ok(RoleResource::collection($roles));
    }

    /** Catálogo de permisos disponibles, agrupados por módulo (para la UI). */
    public function permissions(): JsonResponse
    {
        $grouped = Permission::orderBy('name')->pluck('name')
            ->groupBy(fn (string $name) => explode('.', $name)[0])
            ->map(fn ($items, $module) => [
                'module' => $module,
                'permissions' => $items->map(fn ($name) => [
                    'name' => $name,
                    'action' => explode('.', $name)[1] ?? $name,
                ])->values(),
            ])
            ->values();

        return $this->ok($grouped);
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        $this->flush();

        return $this->created(new RoleResource($role->load('permissions')), 'Rol creado.');
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        if ($role->name === 'Super Administrador') {
            throw new BusinessException('El rol Super Administrador no puede modificarse.');
        }

        $data = $request->validated();
        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);
        $this->flush();

        return $this->ok(new RoleResource($role->load('permissions')), 'Rol actualizado.');
    }

    public function destroy(Role $role): JsonResponse
    {
        if (in_array($role->name, ['Super Administrador', 'Administrador'], true)) {
            throw new BusinessException('Este rol del sistema no puede eliminarse.');
        }

        $assigned = DB::table('model_has_roles')->where('role_id', $role->id)->count();
        if ($assigned > 0) {
            throw new BusinessException('No puedes eliminar un rol con usuarios asignados.');
        }

        $role->delete();
        $this->flush();

        return $this->noContent('Rol eliminado.');
    }

    /** Limpia la caché de permisos de Spatie tras cambios. */
    private function flush(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
