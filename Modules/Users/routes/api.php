<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\Api\RoleController;
use Modules\Users\Http\Controllers\Api\UserController;

/*
| Rutas del módulo Usuarios (prefijo /api/v1). Sesión + permisos por acción.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    // --- Usuarios ---
    Route::controller(UserController::class)->prefix('users')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:users.view');
        Route::post('/', 'store')->middleware('permission:users.create');
        Route::get('{user}', 'show')->whereNumber('user')->middleware('permission:users.view');
        Route::match(['put', 'patch'], '{user}', 'update')->whereNumber('user')->middleware('permission:users.edit');
        Route::patch('{user}/toggle', 'toggle')->whereNumber('user')->middleware('permission:users.edit');
        Route::patch('{user}/password', 'changePassword')->whereNumber('user')->middleware('permission:users.edit');
        Route::delete('{user}', 'destroy')->whereNumber('user')->middleware('permission:users.delete');
    });

    // --- Roles y permisos ---
    Route::controller(RoleController::class)->prefix('roles')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:roles.view');
        Route::get('permissions', 'permissions')->middleware('permission:roles.view');
        Route::post('/', 'store')->middleware('permission:roles.create');
        Route::match(['put', 'patch'], '{role}', 'update')->whereNumber('role')->middleware('permission:roles.edit');
        Route::delete('{role}', 'destroy')->whereNumber('role')->middleware('permission:roles.delete');
    });
});
