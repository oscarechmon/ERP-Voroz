<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\Api\AdjustmentController;
use Modules\Inventory\Http\Controllers\Api\KardexController;
use Modules\Inventory\Http\Controllers\Api\StockController;

/*
| Rutas del módulo Inventario (prefijo /api/v1). Sesión + permisos.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('stock', [StockController::class, 'index'])->middleware('permission:stock.view');
    Route::get('stock/summary', [StockController::class, 'summary'])->middleware('permission:stock.view');

    Route::get('kardex', [KardexController::class, 'index'])->middleware('permission:inventory.view');

    Route::post('inventory/adjustments', [AdjustmentController::class, 'store'])->middleware('permission:inventory.create');
});
