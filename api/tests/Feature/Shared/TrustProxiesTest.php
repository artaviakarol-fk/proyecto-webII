<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class TrustProxiesTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** Behind Caddy, the URLs the API builds use the public HTTPS address, not the internal one. */
    public function test_urls_use_the_scheme_and_host_of_the_proxy(): void
    {
        $negocio = NegocioModel::factory()->create(['logo_path' => 'negocios/x/logo/a.png']);
        Sanctum::actingAs(UserModel::factory()->create(['negocio_id' => $negocio->id]), ['*']);

        $this->getJson('/api/v1/negocio', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => '51-79-10-20.sslip.io',
            'X-Forwarded-Port' => '443',
        ])
            ->assertOk()
            ->assertJsonPath('data.logo', 'https://51-79-10-20.sslip.io/api/v1/negocio/logo');
    }
}
