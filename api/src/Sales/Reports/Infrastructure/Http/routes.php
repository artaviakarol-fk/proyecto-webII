<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Sales\Reports\Infrastructure\Http\Controllers\DailyClosingController;

// Solo la administración ve cuánto se vendió en el día.
Route::get('reportes/cierre-diario', DailyClosingController::class)
    ->middleware(['auth:sanctum', 'negocio', 'abilities:administrar'])
    ->name('reports.daily-closing');
