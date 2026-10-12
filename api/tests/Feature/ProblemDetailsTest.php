<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('devuelve 404 en formato problem+json para rutas de la API que no existen', function () {
    $this->getJson('/api/v1/no-existe')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'about:blank')
        ->assertJsonPath('status', 404)
        ->assertJsonPath('instance', '/api/v1/no-existe');
});

it('devuelve 422 en formato problem+json con los errores de validación', function () {
    Route::post('/api/v1/_prueba-validacion', function (Request $request) {
        $request->validate(['nombre' => 'required']);
    });

    $this->postJson('/api/v1/_prueba-validacion', [])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', 422)
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors' => ['nombre']]);
});

it('devuelve 500 en formato problem+json sin exponer detalles fuera de debug', function () {
    config(['app.debug' => false]);

    Route::get('/api/v1/_prueba-error', function () {
        throw new RuntimeException('detalle interno');
    });

    $this->getJson('/api/v1/_prueba-error')
        ->assertStatus(500)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Error interno del servidor.')
        ->assertJsonMissingPath('detail');
});
