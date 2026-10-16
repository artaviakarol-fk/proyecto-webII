<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Inventory\Expirations\Infrastructure\Http\Controllers\ExpiredUnitsController;

// La bodega y la administración marcan las unidades vencidas que encuentran en el estante.
Route::prefix('productos')
    ->middleware(['auth:sanctum', 'negocio', 'abilities:inventario'])
    ->name('inventory.expirations.')
    ->group(function (): void {
        Route::post('{producto}/vencidos', [ExpiredUnitsController::class, 'store'])->name('store')->whereUuid('producto');
    });
