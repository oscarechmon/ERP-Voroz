<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Commissions\Http\Controllers\Api\CommissionController;
use Modules\Commissions\Http\Controllers\Api\CommissionRuleController;

/*
| Rutas del módulo Comisiones (prefijo /api/v1). Sesión + permisos por acción.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('commissions', [CommissionController::class, 'index'])->middleware('permission:commissions.view');
    Route::post('commissions/pay', [CommissionController::class, 'pay'])->middleware('permission:commissions.pay');

    Route::controller(CommissionRuleController::class)->prefix('commission-rules')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:commissions.view');
        Route::post('/', 'store')->middleware('permission:commissions.edit');
        Route::match(['put', 'patch'], '{rule}', 'update')->whereNumber('rule')->middleware('permission:commissions.edit');
        Route::delete('{rule}', 'destroy')->whereNumber('rule')->middleware('permission:commissions.edit');
    });
});
