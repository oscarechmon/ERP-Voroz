<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Users\Http\Resources\UserResource;

/**
 * Autenticación SPA con Sanctum (sesión por cookie).
 *
 * Flujo: el frontend obtiene la cookie CSRF (/sanctum/csrf-cookie), luego llama a
 * `login`. La sesión queda establecida y `me` devuelve el usuario con sus permisos.
 */
class AuthController extends ApiController
{
    /** Inicia sesión y regenera la sesión (previene fijación de sesión). */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = (bool) $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no coinciden con nuestros registros.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => ['Tu cuenta está desactivada. Contacta al administrador.'],
            ]);
        }

        // Previene fijación de sesión en el flujo SPA (si hay sesión activa).
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Registra el último acceso (auditoría de ingreso).
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        return $this->ok(new UserResource($user->load(['roles', 'permissions'])), 'Sesión iniciada.');
    }

    /** Devuelve el usuario autenticado con roles y permisos. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'permissions']);

        return $this->ok(new UserResource($user));
    }

    /** Cierra la sesión e invalida la cookie. */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->noContent('Sesión cerrada.');
    }
}
