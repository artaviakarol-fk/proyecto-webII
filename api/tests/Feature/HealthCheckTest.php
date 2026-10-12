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

it('responde 200 e indica que la base de datos no está disponible si no responde', function () {
    $this->app->instance(DatabaseStatusChecker::class, new class implements DatabaseStatusChecker
    {
        public function isAvailable(): bool
        {
            return false;
        }
    });

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.app', 'ok')
        ->assertJsonPath('checks.database', 'unavailable');
});
