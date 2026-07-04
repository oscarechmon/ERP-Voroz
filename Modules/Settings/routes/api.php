<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\Api\BranchController;
use Modules\Settings\Http\Controllers\Api\CompanyController;
use Modules\Settings\Http\Controllers\Api\WarehouseController;

/*
| Rutas del módulo Configuración (prefijo /api/v1). Requieren sesión.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    // Almacenes: el listado lo consumen varios módulos (POS/inventario/compras),
    // por eso sólo requiere sesión; la gestión requiere permiso de configuración.
    Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');

    // --- Configuración (empresa, sucursales, almacenes) ---
    Route::middleware('permission:settings.view')->group(function (): void {
        Route::get('settings/company', [CompanyController::class, 'show']);
        Route::get('settings/branches', [BranchController::class, 'index']);
    });

    Route::middleware('permission:settings.edit')->group(function (): void {
        Route::match(['put', 'patch', 'post'], 'settings/company', [CompanyController::class, 'update']);

        Route::post('settings/branches', [BranchController::class, 'store']);
        Route::match(['put', 'patch'], 'settings/branches/{branch}', [BranchController::class, 'update'])->whereNumber('branch');
        Route::delete('settings/branches/{branch}', [BranchController::class, 'destroy'])->whereNumber('branch');

        Route::post('settings/warehouses', [WarehouseController::class, 'store']);
        Route::match(['put', 'patch'], 'settings/warehouses/{warehouse}', [WarehouseController::class, 'update'])->whereNumber('warehouse');
        Route::delete('settings/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->whereNumber('warehouse');
    });
});
