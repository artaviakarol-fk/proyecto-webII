<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Inventory\Products\Infrastructure\Http\Controllers\ProductController;

Route::prefix('productos')
    ->middleware(['auth:sanctum', 'negocio'])
    ->name('inventory.products.')
    ->controller(ProductController::class)
    ->group(function (): void {
        // Todo el personal consulta el catálogo.
        Route::middleware('ability:administrar,caja,inventario')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{producto}', 'show')->name('show')->whereUuid('producto');
        });

        // Solo la administración lo cambia.
        Route::middleware('abilities:administrar')->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::put('{producto}', 'update')->name('update')->whereUuid('producto');
            Route::delete('{producto}', 'destroy')->name('destroy')->whereUuid('producto');
        });
    });
