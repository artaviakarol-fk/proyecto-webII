<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Shared\Infrastructure\Http\Controllers\ApiIndexController;
use Src\Shared\Infrastructure\Http\Controllers\HealthController;

Route::get('/', ApiIndexController::class)->name('api.index');

Route::get('health', HealthController::class)->name('health');
