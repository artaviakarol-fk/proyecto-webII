<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Credit\Customers\Infrastructure\Http\Controllers\CreditIncreaseController;
use Src\Credit\Customers\Infrastructure\Http\Controllers\CreditPaymentController;
use Src\Credit\Customers\Infrastructure\Http\Controllers\CustomerController;
use Src\Credit\Customers\Infrastructure\Http\Controllers\FiadoSaleController;

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

// La caja (cajero y administración) cierra una venta abierta a fiado de un cliente.
Route::post('ventas/{venta}/fiado', [FiadoSaleController::class, 'store'])
    ->middleware(['auth:sanctum', 'negocio', 'abilities:caja'])
    ->whereUuid('venta')
    ->name('credit.fiado.store');

// La caja amplía el límite de un cliente en una emergencia, con motivo y hasta el final del día.
Route::post('clientes/{cliente}/ampliaciones', [CreditIncreaseController::class, 'store'])
    ->middleware(['auth:sanctum', 'negocio', 'ability:administrar,caja'])
    ->whereUuid('cliente')
    ->name('credit.increases.store');

// Solo la administración revisa las ampliaciones y las revierte.
Route::prefix('ampliaciones-limite')
    ->middleware(['auth:sanctum', 'negocio', 'abilities:administrar'])
    ->name('credit.increases.')
    ->controller(CreditIncreaseController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('{ampliacion}/revertir', 'revert')->name('revert')->whereUuid('ampliacion');
    });

// La caja recibe abonos de lo que debe un cliente y entrega el recibo.
Route::middleware(['auth:sanctum', 'negocio', 'ability:administrar,caja'])
    ->name('credit.payments.')
    ->controller(CreditPaymentController::class)
    ->group(function (): void {
        Route::post('clientes/{cliente}/abonos', 'store')->name('store')->whereUuid('cliente');
        Route::get('clientes/{cliente}/abonos', 'index')->name('index')->whereUuid('cliente');
        Route::get('abonos/{abono}', 'show')->name('show')->whereUuid('abono');
        Route::get('abonos/{abono}/recibo', 'receipt')->name('receipt')->whereUuid('abono');
    });
