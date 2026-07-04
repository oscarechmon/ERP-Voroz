<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\Api\DashboardController;

/*
| Rutas del módulo Dashboard (prefijo /api/v1). Sesión + permiso dashboard.view.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('dashboard/metrics', [DashboardController::class, 'metrics'])->middleware('permission:dashboard.view');
});
