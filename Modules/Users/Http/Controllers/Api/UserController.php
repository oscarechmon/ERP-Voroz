<?php

declare(strict_types=1);

namespace Modules\Users\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Users\Http\Requests\StoreUserRequest;
use Modules\Users\Http\Requests\UpdateUserRequest;
use Modules\Users\Http\Resources\UserResource;
use Modules\Users\Services\UserService;

class UserController extends ApiController
{
    public function __construct(private readonly UserService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $users = $this->service->list($request->all());

        return $this->ok(UserResource::collection($users)->response()->getData(true));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        return $this->created(new UserResource($this->service->create($request->validated())), 'Usuario creado.');
    }

    public function show(int $user): JsonResponse
    {
        return $this->ok(new UserResource($this->service->find($user, ['roles', 'permissions'])));
    }

    public function update(UpdateUserRequest $request, int $user): JsonResponse
    {
        return $this->ok(new UserResource($this->service->update($user, $request->validated())), 'Usuario actualizado.');
    }

    public function destroy(int $user): JsonResponse
    {
        $this->service->delete($user);

        return $this->noContent('Usuario eliminado.');
    }

    /** Activa/desactiva el usuario. */
    public function toggle(int $user): JsonResponse
    {
        $model = $this->service->toggleActive($user);

        return $this->ok(new UserResource($model->load('roles')), 'Estado actualizado.');
    }

    /** Cambia la contraseña del usuario. */
    public function changePassword(Request $request, int $user): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:6', 'confirmed']]);
        $this->service->changePassword($user, $data['password']);

        return $this->noContent('Contraseña actualizada.');
    }
}
