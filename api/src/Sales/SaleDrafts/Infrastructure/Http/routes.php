<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Sales\SaleDrafts\Infrastructure\Http\Controllers\SaleDraftController;

// La caja escribe la venta en texto libre y recibe un borrador para revisar; nunca se registra sola.
Route::post('ventas/borrador-ia', SaleDraftController::class)
    ->middleware(['auth:sanctum', 'negocio', 'abilities:caja', 'throttle:20,1'])
    ->name('sales.drafts.store');
