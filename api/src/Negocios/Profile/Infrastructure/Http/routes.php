<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Negocios\Profile\Infrastructure\Http\Controllers\CurrentNegocioController;

Route::get('negocio', CurrentNegocioController::class)
    ->middleware(['auth:sanctum', 'negocio'])
    ->name('negocios.profile.show');
