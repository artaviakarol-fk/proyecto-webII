<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Identity\StaffRoles\Infrastructure\Http\Controllers\StaffRoleController;

// Roles del personal: solo la administración del negocio. Los roles por defecto no se pueden cambiar ni borrar.
Route::prefix('roles')
    ->middleware(['auth:sanctum', 'abilities:administrar', 'negocio'])
    ->name('identity.roles.')
    ->controller(StaffRoleController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{rol}', 'update')->whereUuid('rol')->name('update');
        Route::delete('{rol}', 'destroy')->whereUuid('rol')->name('destroy');
    });
