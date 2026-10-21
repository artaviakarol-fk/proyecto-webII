<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Negocios\OpeningHours\Infrastructure\Http\Controllers\OpeningHoursController;

Route::prefix('negocio/horario')
    ->middleware(['auth:sanctum', 'negocio'])
    ->name('negocios.opening_hours.')
    ->controller(OpeningHoursController::class)
    ->group(function (): void {
        // Todo el personal consulta el horario.
        Route::get('/', 'show')->middleware('ability:administrar,caja,inventario')->name('show');

        // Solo la administración lo cambia.
        Route::put('/', 'update')->middleware('abilities:administrar')->name('update');
    });
