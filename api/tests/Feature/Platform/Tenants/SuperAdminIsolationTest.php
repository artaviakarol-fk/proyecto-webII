<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Tenants;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\NegociosDemoSeeder;
use Database\Seeders\PlataformaDemoSeeder;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

/**
 * The superadmins run the platform, but the data of each negocio is still of the negocio: a real token of a superadmin
 * cannot read the sales, the customers, the fiado or the stock of any negocio.
 */
final class SuperAdminIsolationTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** Every route with the data of a negocio refuses the token of a superadmin. */
    public function test_superadmin_cannot_read_the_data_of_any_negocio(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->postJson('/api/v1/tokens', [
            'correo' => PlataformaDemoSeeder::SUPERADMINS[0][0],
            'contrasena' => NegociosDemoSeeder::PASSWORD,
            'dispositivo' => 'Plataforma',
        ])->assertCreated()->json('data.token');

        foreach (['/productos', '/ventas', '/clientes', '/vencidos', '/reportes/cierre-diario', '/catalogo', '/reservas', '/negocio'] as $route) {
            $this->withToken($token)->getJson('/api/v1'.$route)->assertForbidden();
        }

        $this->withToken($token)->getJson('/api/v1/plataforma/negocios')->assertOk()->assertJsonPath('meta.total', 2);
    }
}
