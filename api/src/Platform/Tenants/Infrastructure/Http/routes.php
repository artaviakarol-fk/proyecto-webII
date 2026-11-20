<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Platform\Tenants\Infrastructure\Http\Controllers\TenantController;

// Plataforma: solo los superadmin. Abren negocios y su primer administrador, pero no ven los datos de ningún negocio.
Route::prefix('plataforma/negocios')
    ->middleware(['auth:sanctum', 'abilities:plataforma'])
    ->name('platform.negocios.')
    ->controller(TenantController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{negocio}', 'show')->whereUuid('negocio')->name('show');
        Route::patch('{negocio}', 'update')->whereUuid('negocio')->name('update');
    });
