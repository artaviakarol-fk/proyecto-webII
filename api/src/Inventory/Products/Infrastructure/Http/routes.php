<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Inventory\Products\Infrastructure\Http\Controllers\ProductController;
use Src\Inventory\Products\Infrastructure\Http\Controllers\ProductPhotoController;

Route::prefix('productos')
    ->middleware(['auth:sanctum', 'negocio'])
    ->name('inventory.products.')
    ->controller(ProductController::class)
    ->group(function (): void {
        // Todo el personal consulta el catálogo.
        Route::middleware('ability:administrar,caja,inventario')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{producto}', 'show')->name('show')->whereUuid('producto');
            Route::get('{producto}/foto', [ProductPhotoController::class, 'show'])->name('photo.show')->whereUuid('producto');
        });

        // Solo la administración lo cambia.
        Route::middleware('abilities:administrar')->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::put('{producto}', 'update')->name('update')->whereUuid('producto');
            Route::delete('{producto}', 'destroy')->name('destroy')->whereUuid('producto');
            Route::post('{producto}/foto', [ProductPhotoController::class, 'store'])->name('photo.store')->whereUuid('producto');
        });
    });
