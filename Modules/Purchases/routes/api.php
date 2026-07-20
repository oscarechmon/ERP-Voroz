<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Purchases\Http\Controllers\Api\PurchaseController;

/*
| Rutas del módulo Compras (prefijo /api/v1). Sesión + permisos.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::controller(PurchaseController::class)->prefix('purchases')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:purchases.view');
        Route::post('/', 'store')->middleware('permission:purchases.create');
        Route::get('{purchase}', 'show')->whereNumber('purchase')->middleware('permission:purchases.view');
        Route::put('{purchase}', 'update')->whereNumber('purchase')->middleware('permission:purchases.edit');
        Route::delete('{purchase}', 'destroy')->whereNumber('purchase')->middleware('permission:purchases.delete');
    });
});
