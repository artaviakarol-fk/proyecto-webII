<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Sales\Sales\Infrastructure\Http\Controllers\SaleController;

// La caja (cajero y administración) vende en efectivo.
Route::prefix('ventas')
    ->middleware(['auth:sanctum', 'negocio', 'abilities:caja'])
    ->name('sales.')
    ->controller(SaleController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{venta}', 'show')->name('show')->whereUuid('venta');
        Route::put('{venta}', 'update')->name('update')->whereUuid('venta');
        Route::post('{venta}/cobrar', 'pay')->name('pay')->whereUuid('venta');
        Route::post('{venta}/anular', 'cancel')->name('cancel')->whereUuid('venta');
    });
