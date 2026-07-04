<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Contacts\Http\Controllers\Api\CustomerController;
use Modules\Contacts\Http\Controllers\Api\SupplierController;

/*
| Rutas del módulo Contactos (prefijo /api/v1). Sesión + permisos por acción.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    // --- Clientes ---
    Route::controller(CustomerController::class)->prefix('customers')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:customers.view');
        Route::post('/', 'store')->middleware('permission:customers.create');
        Route::post('bulk-destroy', 'bulkDestroy')->middleware('permission:customers.delete');
        Route::get('{customer}', 'show')->whereNumber('customer')->middleware('permission:customers.view');
        Route::match(['put', 'patch'], '{customer}', 'update')->whereNumber('customer')->middleware('permission:customers.edit');
        Route::delete('{customer}', 'destroy')->whereNumber('customer')->middleware('permission:customers.delete');
    });

    // --- Proveedores ---
    Route::apiResource('suppliers', SupplierController::class)->only(['index', 'store', 'show', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:suppliers.view')
        ->middlewareFor('show', 'permission:suppliers.view')
        ->middlewareFor('store', 'permission:suppliers.create')
        ->middlewareFor('update', 'permission:suppliers.edit')
        ->middlewareFor('destroy', 'permission:suppliers.delete');
});
