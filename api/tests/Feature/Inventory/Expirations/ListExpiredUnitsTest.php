<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory\Expirations;

use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ListExpiredUnitsTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private NegocioModel $negocio;

    private ProductModel $leche;

    private UserModel $bodeguero;

    /** A negocio with a product and its warehouse worker. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->leche = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Leche entera 1 L', 'stock' => 10]);
        $this->bodeguero = UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $this->negocio->id]);
        Sanctum::actingAs($this->bodeguero, Role::Bodeguero->abilities());
    }

    /** The warehouse sees the units it marked, with the product and who marked them. */
    public function test_lists_the_units_found_expired(): void
    {
        $this->postJson('/api/v1/productos/'.$this->leche->id.'/vencidos', ['cantidad' => 3, 'motivo' => 'Fecha cumplida'])->assertCreated();

        $this->getJson('/api/v1/vencidos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.producto', 'Leche entera 1 L')
            ->assertJsonPath('data.0.cantidad', 3)
            ->assertJsonPath('data.0.motivo', 'Fecha cumplida')
            ->assertJsonPath('data.0.registrada_por', $this->bodeguero->nombre);
    }

    /** Units of another negocio never show up. */
    public function test_does_not_list_units_of_another_negocio(): void
    {
        $this->postJson('/api/v1/productos/'.$this->leche->id.'/vencidos', ['cantidad' => 1, 'motivo' => 'x'])->assertCreated();

        $other = NegocioModel::factory()->create();
        Sanctum::actingAs(UserModel::factory()->role(Role::Bodeguero)->create(['negocio_id' => $other->id]), Role::Bodeguero->abilities());

        $this->getJson('/api/v1/vencidos')->assertOk()->assertJsonCount(0, 'data');
    }

    /** The administration can read the list; the cashier cannot, and an anonymous caller is refused. */
    public function test_only_warehouse_and_administration_can_read(): void
    {
        Sanctum::actingAs(UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->negocio->id]), Role::Cajero->abilities());
        $this->getJson('/api/v1/vencidos')->assertForbidden();

        Sanctum::actingAs(UserModel::factory()->create(['negocio_id' => $this->negocio->id]), Role::Administrador->abilities());
        $this->getJson('/api/v1/vencidos')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/vencidos')->assertUnauthorized();
    }
}
