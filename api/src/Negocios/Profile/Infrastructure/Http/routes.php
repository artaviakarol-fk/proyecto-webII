<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Negocios\Profile\Infrastructure\Http\Controllers\CurrentNegocioController;
use Src\Negocios\Profile\Infrastructure\Http\Controllers\NegocioLogoController;

Route::prefix('negocio')
    ->middleware(['auth:sanctum', 'negocio'])
    ->name('negocios.profile.')
    ->group(function (): void {
        // Only the staff reads the profile of the negocio; the cliente has no token ability for it.
        Route::middleware('ability:administrar,caja,inventario')->group(function (): void {
            Route::get('/', CurrentNegocioController::class)->name('show');
            Route::get('logo', [NegocioLogoController::class, 'show'])->name('logo.show');
        });
        Route::post('logo', [NegocioLogoController::class, 'store'])
            ->middleware('abilities:administrar')
            ->name('logo.store');
    });
