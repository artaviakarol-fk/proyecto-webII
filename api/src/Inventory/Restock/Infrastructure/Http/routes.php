<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Inventory\Restock\Infrastructure\Http\Controllers\RestockController;

// La bodega y la administración ven qué productos reponer; el cajero no.
Route::get('inventario/reposicion', RestockController::class)
    ->middleware(['auth:sanctum', 'negocio', 'abilities:inventario'])
    ->name('inventory.restock');
