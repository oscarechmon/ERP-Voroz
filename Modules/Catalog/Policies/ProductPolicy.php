<?php

declare(strict_types=1);

namespace Modules\Catalog\Policies;

use App\Models\User;

/**
 * Policy de Productos. La autorización principal se aplica por permiso en las
 * rutas; esta policy permite usar Gates (`$user->can('update', $product)`) y
 * reglas más finas a nivel de registro cuando se necesiten.
 */
class ProductPolicy
{
    /** El Super Administrador siempre puede (short-circuit del Gate). */
    public function before(User $user): ?bool
    {
        return $user->hasRole('Super Administrador') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user): bool
    {
        return $user->can('products.edit');
    }

    public function delete(User $user): bool
    {
        return $user->can('products.delete');
    }
}
