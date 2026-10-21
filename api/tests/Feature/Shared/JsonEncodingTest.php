<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class JsonEncodingTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** Accents travel as UTF-8 and slashes without a backslash, in data and in problem documents. */
    public function test_json_keeps_accents_and_slashes_as_they_are(): void
    {
        $negocio = NegocioModel::factory()->create();
        $admin = UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $negocio->id]);
        ProductModel::factory()->create(['negocio_id' => $negocio->id, 'nombre' => 'Café molido 250 g']);
        Sanctum::actingAs($admin, Role::Administrador->abilities());

        $listing = (string) $this->getJson('/api/v1/productos')->assertOk()->getContent();
        $problem = (string) $this->getJson('/api/v1/productos/0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99')->assertNotFound()->getContent();

        $this->assertStringContainsString('"Café molido 250 g"', $listing);
        $this->assertStringNotContainsString('\\u00e9', $listing);
        $this->assertStringContainsString('http://localhost/problemas/', $problem);
    }
}
