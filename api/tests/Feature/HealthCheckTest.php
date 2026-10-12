<?php

use App\Application\Health\DatabaseStatusChecker;

it('responde 200 con el estado de la app y de la base de datos', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.app', 'ok')
        ->assertJsonPath('checks.database', 'ok')
        ->assertJsonStructure(['status', 'checks' => ['app', 'database'], 'checked_at']);
});

it('responde 503 en formato problem+json si la base de datos no responde', function () {
    $this->app->instance(DatabaseStatusChecker::class, new class implements DatabaseStatusChecker
    {
        public function isAvailable(): bool
        {
            return false;
        }
    });

    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', 503)
        ->assertJsonPath('title', 'Servicio no disponible.')
        ->assertJsonPath('checks.database', 'unavailable');
});
