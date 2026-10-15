<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Identity\Users\Infrastructure\Http\Controllers\TokenController;

Route::post('tokens', [TokenController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('identity.tokens.store');
