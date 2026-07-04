<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Cashbox\Http\Controllers\Api\CashboxController;

/*
| Rutas del módulo Caja (prefijo /api/v1). Sesión + permisos.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::controller(CashboxController::class)->prefix('cashbox')->group(function (): void {
        Route::get('current', 'current')->middleware('permission:cashbox.view');
        Route::get('history', 'history')->middleware('permission:cashbox.view');
        Route::post('open', 'open')->middleware('permission:cashbox.create');
        Route::post('movement', 'movement')->middleware('permission:cashbox.create');
        Route::post('close', 'close')->middleware('permission:cashbox.edit');
    });
});
