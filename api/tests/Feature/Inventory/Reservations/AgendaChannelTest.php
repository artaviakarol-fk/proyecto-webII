<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory\Reservations;

use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

/**
 * Quién puede escuchar la agenda en vivo de un negocio. La prueba corre con el broadcaster de Reverb (ver phpunit.xml):
 * con "null" la ruta de autorización no evalúa ninguna regla y la prueba de 403 pasaría aunque el canal estuviera abierto.
 */
final class AgendaChannelTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private NegocioModel $teresita;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teresita = NegocioModel::factory()->create();
    }

    /** Ask for permission to listen to the agenda channel of a negocio. */
    private function askPermission(NegocioModel $negocio): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-negocio.{$negocio->id}.agenda",
        ]);
    }

    /** Act as an account of a negocio with the abilities of its role. */
    private function actAs(Role $role, NegocioModel $negocio): void
    {
        Sanctum::actingAs(UserModel::factory()->role($role)->create(['negocio_id' => $negocio->id]), $role->abilities());
    }

    /** The staff of the register of the negocio listens to its own agenda. */
    public function test_the_staff_of_the_negocio_can_listen_to_its_agenda(): void
    {
        foreach ([Role::Administrador, Role::Cajero, Role::Supervisor] as $role) {
            $this->actAs($role, $this->teresita);

            $this->askPermission($this->teresita)->assertOk()->assertJsonStructure(['auth']);
        }
    }

    /** The staff of another negocio gets a 403: changing the id in the channel name is not enough. */
    public function test_the_staff_of_another_negocio_gets_403(): void
    {
        $otro = NegocioModel::factory()->create();
        $this->actAs(Role::Cajero, $otro);

        $this->askPermission($this->teresita)->assertForbidden();
    }

    /** A cliente of the same negocio is not part of the staff: 403. */
    public function test_a_cliente_gets_403(): void
    {
        $this->actAs(Role::Cliente, $this->teresita);

        $this->askPermission($this->teresita)->assertForbidden();
    }

    /** Without a token there is no permission at all. */
    public function test_without_a_token_it_answers_401(): void
    {
        $this->askPermission($this->teresita)->assertUnauthorized();
    }
}
