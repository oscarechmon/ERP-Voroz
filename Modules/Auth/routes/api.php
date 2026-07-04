<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Api\AuthController;

/*
| Rutas de autenticación (prefijo global /api/v1 aplicado por el módulo).
| `login` es público; `me` y `logout` requieren sesión activa (Sanctum).
*/
Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1') // Rate limit: mitiga fuerza bruta.
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    });
});
