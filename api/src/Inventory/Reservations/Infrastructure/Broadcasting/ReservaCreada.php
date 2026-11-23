<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A cliente held units of a product: the agenda of the staff of that negocio shows it without reloading.
 *
 * It travels through the queue and only after the commit, so a reserva that was rolled back is never announced.
 */
final class ReservaCreada implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /** Only plain values: the event is serialized into the queue. */
    public function __construct(
        public readonly string $reservaId,
        public readonly string $negocioId,
        public readonly string $productoId,
        public readonly int $cantidad,
        public readonly string $expiraEn,
    ) {}

    /** The private channel of the agenda of THAT negocio. */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("negocio.{$this->negocioId}.agenda");
    }

    /** Name the agenda listens to. */
    public function broadcastAs(): string
    {
        return 'reserva.creada';
    }

    /**
     * The least the agenda needs: no name, email or phone of the cliente.
     *
     * @return array{id: string, producto_id: string, cantidad: int, expira_en: string, estado: string}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->reservaId,
            'producto_id' => $this->productoId,
            'cantidad' => $this->cantidad,
            'expira_en' => $this->expiraEn,
            'estado' => 'activa',
        ];
    }
}
