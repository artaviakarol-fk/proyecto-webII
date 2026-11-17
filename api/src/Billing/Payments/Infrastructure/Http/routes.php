<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Billing\Payments\Infrastructure\Gateways\PaymentGateways;
use Src\Billing\Payments\Infrastructure\Http\Controllers\GatewayOptionsController;
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

// La caja y el cliente ven qué pasarelas y medios de pago hay para elegir uno en cada pago.
Route::get('pagos/pasarelas', GatewayOptionsController::class)
    ->middleware(['auth:sanctum', 'ability:caja,comprar'])
    ->name('billing.payments.gateways');

// La pasarela avisa el resultado de un cobro. No hay token: la URL dice a qué negocio pertenece el aviso y la firma o el
// secreto de la pasarela prueba que es suyo. El estado real se le pregunta a la pasarela, nunca se lee del aviso.
// Sin nombre de pasarela se usa la configurada, así las direcciones que ya se dieron a las pasarelas siguen funcionando.
Route::post('webhooks/pagos/{negocio}', PaymentWebhookController::class)
    ->middleware(ScopeWebhookToNegocio::class)
    ->whereUuid('negocio')
    ->name('billing.payments.webhook');

// La misma notificación, con la pasarela nombrada en la dirección (`/onvo` o `/paypal`): se verifica con esa pasarela.
Route::post('webhooks/pagos/{negocio}/{pasarela}', PaymentWebhookController::class)
    ->middleware(ScopeWebhookToNegocio::class)
    ->whereUuid('negocio')
    ->whereIn('pasarela', PaymentGateways::PROVIDERS)
    ->name('billing.payments.webhook.provider');
