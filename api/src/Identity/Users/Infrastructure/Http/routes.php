<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Identity\Users\Infrastructure\Http\Controllers\TokenController;

Route::post('tokens', [TokenController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('identity.tokens.store');

// Sesión de la cuenta que hace la petición: quién es y cerrar la sesión.
Route::middleware('auth:sanctum')
    ->name('identity.')
    ->controller(TokenController::class)
    ->group(function (): void {
        Route::get('usuario', 'show')->name('user.show');
        Route::delete('tokens/actual', 'destroyCurrent')->name('tokens.destroy-current');
        Route::delete('tokens', 'destroyAll')->name('tokens.destroy-all');
    });
