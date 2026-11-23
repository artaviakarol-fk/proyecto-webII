<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Shared\Infrastructure\Http\Controllers\ApiIndexController;
use Src\Shared\Infrastructure\Http\Controllers\BroadcastingAuthController;
use Src\Shared\Infrastructure\Http\Controllers\HealthController;

Route::get('/', ApiIndexController::class)->name('api.index');

Route::get('health', HealthController::class)->name('health');

// Permiso para escuchar un canal privado de Reverb, con el mismo token de la API. Las reglas están en routes/channels.php.
Route::post('broadcasting/auth', BroadcastingAuthController::class)->middleware('auth:sanctum')->name('broadcasting.auth');
