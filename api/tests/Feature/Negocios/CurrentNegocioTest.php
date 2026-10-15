<?php

declare(strict_types=1);

namespace Tests\Feature\Negocios;

use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class CurrentNegocioTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/negocio';

    /** The account sees the profile of its own negocio. */
    public function test_account_sees_its_own_negocio(): void
    {
        $negocio = NegocioModel::factory()->create(['nombre' => 'Minisúper Teresita', 'logo_path' => 'logos/teresita.png']);
        Sanctum::actingAs(UserModel::factory()->create(['negocio_id' => $negocio->id]), ['*']);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $negocio->id,
                'nombre' => 'Minisúper Teresita',
                'logo' => 'logos/teresita.png',
                'activo' => true,
            ]]);
    }

    /** Without a token the profile answers the unauthenticated problem. */
    public function test_guest_receives_an_unauthenticated_problem(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'http://localhost/problemas/no-autenticado');
    }
}
