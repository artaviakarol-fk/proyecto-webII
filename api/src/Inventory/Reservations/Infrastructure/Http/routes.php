<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Inventory\Reservations\Infrastructure\Http\Controllers\ReservationController;

// El cliente aparta unidades por un rato antes de pagar; solo ve y suelta sus propias reservas.
Route::prefix('reservas')
    ->middleware(['auth:sanctum', 'negocio', 'abilities:comprar'])
    ->name('inventory.reservations.')
    ->controller(ReservationController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::delete('{reserva}', 'destroy')->name('destroy')->whereUuid('reserva');
    });
