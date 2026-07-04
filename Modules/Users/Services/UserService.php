<?php

declare(strict_types=1);

namespace Modules\Users\Services;

use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Modules\Users\Repositories\UserRepository;

/**
 * Lógica de negocio de usuarios: alta con contraseña hasheada y rol, actualización,
 * cambio de contraseña y activación/desactivación. Protege al Super Administrador.
 */
class UserService extends BaseService
{
    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct($users);
    }

    public function create(array $data): Model
    {
        $role = $data['role'] ?? null;
        unset($data['role']);

        $data['password'] = Hash::make($data['password']);
        $data['company_id'] = $data['company_id'] ?? optional(auth()->user())->company_id;
        $data['branch_id'] = $data['branch_id'] ?? optional(auth()->user())->branch_id;

        /** @var User $user */
        $user = $this->users->create($data);

        if ($role) {
            $user->syncRoles([$role]);
        }

        return $user->load('roles');
    }

    public function update(int|string $id, array $data): Model
    {
        $role = $data['role'] ?? null;
        unset($data['role']);

        // La contraseña sólo se actualiza si se envía explícitamente.
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        /** @var User $user */
        $user = $this->users->update($id, $data);

        if ($role) {
            $user->syncRoles([$role]);
        }

        return $user->load('roles');
    }

    /** Cambia la contraseña de un usuario. */
    public function changePassword(int $id, string $password): Model
    {
        return $this->users->update($id, ['password' => Hash::make($password)]);
    }

    /** Activa/desactiva un usuario, impidiendo desactivar al último Super Admin. */
    public function toggleActive(int $id): Model
    {
        /** @var User $user */
        $user = $this->users->findOrFail($id);

        if ($user->is_active && $this->isLastSuperAdmin($user)) {
            throw new BusinessException('No puedes desactivar al último Super Administrador.');
        }

        return $this->users->update($id, ['is_active' => ! $user->is_active]);
    }

    public function delete(int|string $id): bool
    {
        /** @var User $user */
        $user = $this->users->findOrFail($id);

        if ($this->isLastSuperAdmin($user)) {
            throw new BusinessException('No puedes eliminar al último Super Administrador.');
        }

        if ((int) $id === (int) auth()->id()) {
            throw new BusinessException('No puedes eliminar tu propia cuenta.');
        }

        return $this->users->delete($id);
    }

    private function isLastSuperAdmin(User $user): bool
    {
        if (! $user->hasRole('Super Administrador')) {
            return false;
        }

        return User::role('Super Administrador')->where('is_active', true)->count() <= 1;
    }
}
