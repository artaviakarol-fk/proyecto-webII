<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Sales\Purchases\Infrastructure\Http\Controllers\PurchaseController;

// El cliente compra lo que reservó y paga con la pasarela que elige; solo ve el pago de sus propias compras.
Route::prefix('compras')
    ->middleware(['auth:sanctum', 'negocio', 'abilities:comprar'])
    ->name('sales.purchases.')
    ->controller(PurchaseController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
        Route::get('{venta}/pago', 'show')->name('show')->whereUuid('venta');
    });
