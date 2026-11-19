<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Identity\Staff\Infrastructure\Http\Controllers\StaffController;

// Personal del negocio: solo la administración. Cada negocio ve y maneja únicamente a su propio personal.
Route::prefix('personal')
    ->middleware(['auth:sanctum', 'abilities:administrar', 'negocio'])
    ->name('identity.staff.')
    ->controller(StaffController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('{usuario}', 'update')->whereUuid('usuario')->name('update');
        Route::post('{usuario}/contrasena-temporal', 'resetPassword')->whereUuid('usuario')->name('reset-password');
    });
