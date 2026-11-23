<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class ReservaCreada implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /** Only simple data: the event travels through the queue. */
    public function __construct(
        public readonly string $negocioId,
        public readonly string $reservaId,
        public readonly string $producto,
        public readonly int $cantidad,
        public readonly string $expiraEn,
    ) {}

    /** The private channel of THIS negocio: the staff of another negocio cannot listen. */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("negocio.{$this->negocioId}.agenda");
    }

    /** Name that the screen listens to as .reserva.creada */
    public function broadcastAs(): string
    {
        return 'reserva.creada';
    }

    /** Only what the screen paints: no data of the cliente. */
    public function broadcastWith(): array
    {
        return ['id' => $this->reservaId, 'producto' => $this->producto, 'cantidad' => $this->cantidad, 'expira_en' => $this->expiraEn];
    }
}
