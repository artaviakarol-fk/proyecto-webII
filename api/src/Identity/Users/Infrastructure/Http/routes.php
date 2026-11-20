<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Identity\Users\Infrastructure\Http\Controllers\ClienteRegistrationController;
use Src\Identity\Users\Infrastructure\Http\Controllers\TokenController;

Route::post('tokens', [TokenController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('identity.tokens.store');

// Registro público: una persona abre su cuenta de cliente en un negocio. No pide token, por eso va limitado.
Route::post('negocios/{negocio}/registro', ClienteRegistrationController::class)
    ->middleware('throttle:10,1')
    ->whereUuid('negocio')
    ->name('identity.registration.store');

// Sesión de la cuenta que hace la petición: quién es y cerrar la sesión.
Route::middleware('auth:sanctum')
    ->name('identity.')
    ->controller(TokenController::class)
    ->group(function (): void {
        Route::get('usuario', 'show')->name('user.show');
        Route::put('usuario/contrasena', 'updatePassword')->middleware('throttle:10,1')->name('password.update');
        Route::delete('tokens/actual', 'destroyCurrent')->name('tokens.destroy-current');
        Route::delete('tokens', 'destroyAll')->name('tokens.destroy-all');
    });
