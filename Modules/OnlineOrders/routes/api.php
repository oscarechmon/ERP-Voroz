<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\OnlineOrders\Http\Controllers\Api\OnlineOrderController;

/*
| Rutas del módulo Pedidos online (prefijo /api/v1). Sesión + permisos.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::controller(OnlineOrderController::class)->prefix('online-orders')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:online_orders.view');
        Route::get('{order}', 'show')->whereNumber('order')->middleware('permission:online_orders.view');
        Route::post('{order}/status', 'status')->whereNumber('order')->middleware('permission:online_orders.edit');
    });
});
