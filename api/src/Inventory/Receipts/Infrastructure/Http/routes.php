<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Inventory\Receipts\Infrastructure\Http\Controllers\GoodsReceiptController;

// La bodega y la administración registran la mercadería que llega.
Route::prefix('entradas')
    ->middleware(['auth:sanctum', 'negocio', 'abilities:inventario'])
    ->name('inventory.receipts.')
    ->controller(GoodsReceiptController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
        Route::get('{entrada}', 'show')->name('show')->whereUuid('entrada');
    });
