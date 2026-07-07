<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\Api\SaleController;

/*
| Rutas del módulo Ventas (prefijo /api/v1). Sesión + permisos.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::controller(SaleController::class)->prefix('sales')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:sales.view');
        Route::post('/', 'store')->middleware('permission:sales.create');
        Route::get('{sale}', 'show')->whereNumber('sale')->middleware('permission:sales.view');
        Route::get('{sale}/ticket', 'ticket')->whereNumber('sale')->middleware('permission:sales.print');
        Route::post('{sale}/cancel', 'cancel')->whereNumber('sale')->middleware('permission:sales.cancel');
    });
});
