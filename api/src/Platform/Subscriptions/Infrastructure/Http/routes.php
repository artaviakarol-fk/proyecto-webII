<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Billing\Payments\Infrastructure\Gateways\PaymentGateways;
use Src\Platform\Subscriptions\Infrastructure\Http\Controllers\ManualSubscriptionPaymentController;
use Src\Platform\Subscriptions\Infrastructure\Http\Controllers\SubscriptionController;
use Src\Platform\Subscriptions\Infrastructure\Http\Controllers\SubscriptionWebhookController;

// Alquiler del negocio: lo ve y lo paga su administración, aun con el servicio suspendido.
Route::prefix('suscripcion')
    ->middleware(['auth:sanctum', 'abilities:administrar', 'negocio'])
    ->name('platform.subscription.')
    ->controller(SubscriptionController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('show');
        Route::post('pagos', 'pay')->middleware('throttle:10,1')->name('pay');
        Route::get('pagos/{pago}', 'refresh')->whereUuid('pago')->name('refresh');
    });

// Pagos en efectivo o por SINPE al número de la plataforma: los registra un superadmin.
Route::post('plataforma/negocios/{negocio}/pagos', ManualSubscriptionPaymentController::class)
    ->middleware(['auth:sanctum', 'abilities:plataforma'])
    ->whereUuid('negocio')
    ->name('platform.payments.store');

// Notificación del proveedor sobre un pago del alquiler: se verifica y es idempotente.
Route::post('webhooks/suscripciones/{pasarela}', SubscriptionWebhookController::class)
    ->middleware('throttle:60,1')
    ->whereIn('pasarela', PaymentGateways::PROVIDERS)
    ->name('platform.webhooks.subscription');
