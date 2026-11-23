<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class ReservaEstado implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /** Only simple data: the event travels through the queue. */
    public function __construct(
        public readonly string $negocioId,
        public readonly string $reservaId,
        public readonly string $estado,
    ) {}

    /** The private channel of THIS negocio: the staff of another negocio cannot listen. */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("negocio.{$this->negocioId}.agenda");
    }

    /** Name that the screen listens to as .reserva.estado */
    public function broadcastAs(): string
    {
        return 'reserva.estado';
    }

    /** The id and the new state, nothing else. */
    public function broadcastWith(): array
    {
        return ['id' => $this->reservaId, 'estado' => $this->estado];
    }
}
