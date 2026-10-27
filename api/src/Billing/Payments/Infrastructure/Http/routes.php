<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Billing\Payments\Infrastructure\Http\Controllers\SalePaymentController;

// La caja (cajero y administración) cobra una venta con tarjeta o SINPE Móvil; solo la administración reembolsa.
Route::prefix('ventas/{venta}/pago')
    ->middleware(['auth:sanctum', 'negocio', 'abilities:caja'])
    ->whereUuid('venta')
    ->name('billing.payments.')
    ->controller(SalePaymentController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
        Route::get('/', 'show')->name('show');
        Route::post('/reembolso', 'refund')->middleware('abilities:administrar')->name('refund');
    });
