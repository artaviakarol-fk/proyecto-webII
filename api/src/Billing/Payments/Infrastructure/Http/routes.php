<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Billing\Payments\Infrastructure\Http\Controllers\PaymentWebhookController;
use Src\Billing\Payments\Infrastructure\Http\Controllers\SalePaymentController;
use Src\Billing\Payments\Infrastructure\Http\Middleware\ScopeWebhookToNegocio;

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

// La pasarela avisa el resultado de un cobro. No hay token: la URL dice a qué negocio pertenece el aviso y la firma o el
// secreto de la pasarela prueba que es suyo. El estado real se le pregunta a la pasarela, nunca se lee del aviso.
Route::post('webhooks/pagos/{negocio}', PaymentWebhookController::class)
    ->middleware(ScopeWebhookToNegocio::class)
    ->whereUuid('negocio')
    ->name('billing.payments.webhook');
