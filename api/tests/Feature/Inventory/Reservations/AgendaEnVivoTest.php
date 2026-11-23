<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory\Reservations;

use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Inventory\Reservations\Infrastructure\Broadcasting\ReservaCancelada;
use Src\Inventory\Reservations\Infrastructure\Broadcasting\ReservaCreada;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

/**
 * La agenda del personal se entera de las reservas sin recargar. El canal es privado y por negocio: solo el
 * personal de ese negocio recibe el permiso firmado para escucharlo; un cliente o el personal de otro negocio
 * recibe 403. Si la regla del canal se cambia a «return true», las pruebas de 403 fallan.
 */
final class AgendaEnVivoTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private NegocioModel $teresita;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teresita = NegocioModel::factory()->create();
    }

    public function test_el_personal_del_negocio_puede_escuchar_su_agenda(): void
    {
        $this->actAs(UserModel::factory()->role(Role::Cajero)->create(['negocio_id' => $this->teresita->id]));

        $this->pedirPermiso($this->teresita)->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_el_personal_de_otro_negocio_recibe_403(): void
    {
        $otro = NegocioModel::factory()->create();
        $this->actAs(UserModel::factory()->role(Role::Administrador)->create(['negocio_id' => $otro->id]));

        $this->pedirPermiso($this->teresita)->assertForbidden();
    }

    public function test_un_cliente_del_negocio_recibe_403(): void
    {
        $this->actAs(UserModel::factory()->role(Role::Cliente)->create(['negocio_id' => $this->teresita->id]));

        $this->pedirPermiso($this->teresita)->assertForbidden();
    }

    public function test_sin_token_recibe_401(): void
    {
        $this->pedirPermiso($this->teresita)->assertUnauthorized();
    }

    public function test_una_reserva_nueva_se_transmite_a_la_agenda_de_su_negocio_sin_datos_del_cliente(): void
    {
        Event::fake([ReservaCreada::class, ReservaCancelada::class]);
        $leche = ProductModel::factory()->create(['negocio_id' => $this->teresita->id, 'precio_venta' => '950.00', 'stock' => 5]);
        $carla = UserModel::factory()->role(Role::Cliente)->create([
            'negocio_id' => $this->teresita->id, 'nombre' => 'Carla Jiménez', 'correo' => 'carla@correo.test',
        ]);
        $this->actAs($carla);

        $this->postJson('/api/v1/reservas', ['producto_id' => $leche->id, 'cantidad' => 2])->assertCreated();

        Event::assertDispatched(ReservaCreada::class, function (ReservaCreada $evento) use ($leche): bool {
            $carga = (string) json_encode($evento->broadcastWith());

            return $evento->broadcastOn()->name === "private-negocio.{$this->teresita->id}.agenda"
                && $evento->broadcastAs() === 'reserva.creada'
                && $evento->broadcastWith()['producto_id'] === $leche->id
                && $evento->broadcastWith()['cantidad'] === 2
                && ! str_contains($carga, 'carla@correo.test')
                && ! str_contains($carga, 'Carla');
        });
    }

    public function test_soltar_una_reserva_se_transmite_a_la_agenda_de_su_negocio(): void
    {
        Event::fake([ReservaCreada::class, ReservaCancelada::class]);
        $leche = ProductModel::factory()->create(['negocio_id' => $this->teresita->id, 'precio_venta' => '950.00', 'stock' => 5]);
        $this->actAs(UserModel::factory()->role(Role::Cliente)->create(['negocio_id' => $this->teresita->id]));
        $reserva = $this->postJson('/api/v1/reservas', ['producto_id' => $leche->id, 'cantidad' => 1])->json('data.id');

        $this->deleteJson("/api/v1/reservas/{$reserva}")->assertNoContent();

        Event::assertDispatched(ReservaCancelada::class, fn (ReservaCancelada $evento): bool => $evento->reservaId === $reserva
            && $evento->broadcastOn()->name === "private-negocio.{$this->teresita->id}.agenda"
            && $evento->broadcastWith() === ['id' => $reserva, 'estado' => 'cancelada']);
    }

    /** Ask for the signed permission to listen to the agenda of a negocio. */
    private function pedirPermiso(NegocioModel $negocio): TestResponse
    {
        return $this->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-negocio.{$negocio->id}.agenda",
        ]);
    }

    /** Act as the account with the abilities its role gives to its tokens. */
    private function actAs(UserModel $usuario): void
    {
        Sanctum::actingAs($usuario, Role::from($usuario->rol)->abilities());
    }
}
