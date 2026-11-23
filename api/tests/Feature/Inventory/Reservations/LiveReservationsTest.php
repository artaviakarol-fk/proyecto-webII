<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory\Reservations;

use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Inventory\Reservations\Infrastructure\Broadcasting\ReservaCreada;
use Src\Inventory\Reservations\Infrastructure\Broadcasting\ReservaEstado;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

/** La agenda en vivo: cada reserva que entra o se suelta se transmite al canal privado de su negocio. */
final class LiveReservationsTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private NegocioModel $negocio;

    private UserModel $ana;

    private ProductModel $leche;

    /** A product with 5 units and a cliente with personal data that must never travel in the events. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
        $this->leche = ProductModel::factory()->create(['negocio_id' => $this->negocio->id, 'nombre' => 'Leche entera 1 L', 'precio_venta' => '950.00', 'stock' => 5]);
        $this->ana = UserModel::factory()->role(Role::Cliente)->create([
            'negocio_id' => $this->negocio->id,
            'nombre' => 'Ana Quesada Mora',
            'correo' => 'ana.quesada@correo.test',
        ]);
        Sanctum::actingAs($this->ana, Role::Cliente->abilities());
    }

    /** A new reserva reaches the private channel of its negocio, with the minimum the screen paints. */
    public function test_a_new_reserva_is_broadcast_to_the_agenda_of_its_negocio_without_personal_data(): void
    {
        Event::fake([ReservaCreada::class]);

        $this->postJson('/api/v1/reservas', ['producto_id' => $this->leche->id, 'cantidad' => 2])->assertCreated();

        Event::assertDispatched(ReservaCreada::class, function (ReservaCreada $event): bool {
            $payload = (string) json_encode($event->broadcastWith());

            return $event->broadcastOn()->name === "private-negocio.{$this->negocio->id}.agenda"
                && $event->broadcastAs() === 'reserva.creada'
                && $event->broadcastWith()['cantidad'] === 2
                && $event->broadcastWith()['producto'] === 'Leche entera 1 L'
                && ! str_contains($payload, 'ana.quesada@correo.test')
                && ! str_contains($payload, 'Ana Quesada');
        });
    }

    /** Letting a reserva go tells the agenda that it changed its state. */
    public function test_cancelling_a_reserva_broadcasts_its_new_state(): void
    {
        $id = $this->postJson('/api/v1/reservas', ['producto_id' => $this->leche->id, 'cantidad' => 1])->assertCreated()->json('data.id');
        Event::fake([ReservaEstado::class]);

        $this->deleteJson("/api/v1/reservas/{$id}")->assertNoContent();

        Event::assertDispatched(ReservaEstado::class, fn (ReservaEstado $event): bool => $event->broadcastOn()->name === "private-negocio.{$this->negocio->id}.agenda"
            && $event->broadcastWith() === ['id' => $id, 'estado' => 'cancelada']);
    }

    /** A reserva that is refused (more units than the stock) is not announced. */
    public function test_a_refused_reserva_is_not_broadcast(): void
    {
        Event::fake([ReservaCreada::class]);

        $this->postJson('/api/v1/reservas', ['producto_id' => $this->leche->id, 'cantidad' => 50])->assertStatus(409);

        Event::assertNotDispatched(ReservaCreada::class);
    }
}
