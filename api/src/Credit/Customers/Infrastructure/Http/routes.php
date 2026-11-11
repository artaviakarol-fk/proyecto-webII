<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Credit\Customers\Infrastructure\Http\Controllers\CustomerController;

Route::prefix('clientes')
    ->middleware(['auth:sanctum', 'negocio'])
    ->name('credit.customers.')
    ->controller(CustomerController::class)
    ->group(function (): void {
        // La caja consulta a los clientes y lo que deben, para vender a fiado y recibir abonos.
        Route::middleware('ability:administrar,caja')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{cliente}', 'show')->name('show')->whereUuid('cliente');
        });

        // Solo la administración registra clientes, define su límite y los desactiva.
        Route::middleware('abilities:administrar')->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::put('{cliente}', 'update')->name('update')->whereUuid('cliente');
            Route::delete('{cliente}', 'destroy')->name('destroy')->whereUuid('cliente');
        });
    });
