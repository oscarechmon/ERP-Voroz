<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Packages\Http\Controllers\Api\CustomerPackageController;
use Modules\Packages\Http\Controllers\Api\PackageController;

/*
| Rutas del módulo Paquetes (prefijo /api/v1). Sesión + permisos por acción.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('packages', PackageController::class)->only(['index', 'store', 'show', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:packages.view')
        ->middlewareFor('show', 'permission:packages.view')
        ->middlewareFor('store', 'permission:packages.create')
        ->middlewareFor('update', 'permission:packages.edit')
        ->middlewareFor('destroy', 'permission:packages.delete');

    Route::controller(CustomerPackageController::class)->prefix('customer-packages')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:packages.view');
        Route::get('{customerPackage}', 'show')->whereNumber('customerPackage')->middleware('permission:packages.view');
        Route::post('/', 'store')->middleware('permission:packages.assign');
    });
});
