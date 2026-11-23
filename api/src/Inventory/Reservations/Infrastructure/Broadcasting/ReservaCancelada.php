<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A cliente let go a reserva: its units are free again and the agenda of the staff drops it. */
final class ReservaCancelada implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /** Only plain values: the event is serialized into the queue. */
    public function __construct(
        public readonly string $reservaId,
        public readonly string $negocioId,
    ) {}

    /** The private channel of the agenda of THAT negocio. */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("negocio.{$this->negocioId}.agenda");
    }

    /** Name the agenda listens to. */
    public function broadcastAs(): string
    {
        return 'reserva.cancelada';
    }

    /**
     * The id and the new estado, nothing else.
     *
     * @return array{id: string, estado: string}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->reservaId, 'estado' => 'cancelada'];
    }
}
