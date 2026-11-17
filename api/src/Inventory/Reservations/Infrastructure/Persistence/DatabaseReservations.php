<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Inventory\Products\Domain\Exceptions\ProductNotFoundException;
use Src\Inventory\Reservations\Application\Contracts\ReservationReader;
use Src\Inventory\Reservations\Application\DTOs\ReservationDetails;
use Src\Inventory\Reservations\Domain\Contracts\ReservationRepository;
use Src\Inventory\Reservations\Domain\Entities\Reservation;
use Src\Inventory\Reservations\Domain\Exceptions\ReservationExceedsAvailableException;
use Src\Inventory\Reservations\Domain\ValueObjects\ReservationId;
use Src\Inventory\Reservations\Domain\ValueObjects\ReservationStatus;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class DatabaseReservations implements ReservationReader, ReservationRepository
{
    private const string EXPIRED_ON_THE_SHELF = 'activa';

    /** Generate a time-ordered UUID for a new reserva. */
    public function nextId(): ReservationId
    {
        return new ReservationId((string) Str::uuid7());
    }

    /**
     * Store the reserva after locking the product row with SELECT ... FOR UPDATE, the same lock a sale takes.
     * While the row is locked nobody else can reserve or sell that product, so the units that are free are
     * counted and taken without anybody in between: of two clientes asking for the last unit, the second one
     * waits for the first and then finds no unit left.
     */
    public function save(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation): void {
            $negocioId = $reservation->negocioId->value;
            $productId = $reservation->productId->value;

            $product = DB::table('productos')
                ->where('negocio_id', $negocioId)
                ->where('id', $productId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first() ?? throw ProductNotFoundException::create();

            // Reserving a product again replaces what the cliente held of it, so its old units count as free.
            DB::table('reservas')
                ->where('negocio_id', $negocioId)
                ->where('producto_id', $productId)
                ->where('usuario_id', $reservation->userId)
                ->where('estado', ReservationStatus::Active->value)
                ->update(['estado' => ReservationStatus::Cancelled->value, 'updated_at' => $reservation->reservedAt]);

            $expired = (int) DB::table('unidades_vencidas')
                ->where('negocio_id', $negocioId)
                ->where('producto_id', $productId)
                ->where('estado', self::EXPIRED_ON_THE_SHELF)
                ->sum('cantidad');

            $held = (int) DB::table('reservas')
                ->where('negocio_id', $negocioId)
                ->where('producto_id', $productId)
                ->where('estado', ReservationStatus::Active->value)
                ->where('expira_en', '>', $reservation->reservedAt)
                ->sum('cantidad');

            $available = max(0, (int) $product->stock - $expired - $held);

            if ($reservation->quantity > $available) {
                throw ReservationExceedsAvailableException::create((string) $product->nombre, $reservation->quantity, $available);
            }

            DB::table('reservas')->insert([
                'id' => $reservation->id->value,
                'negocio_id' => $negocioId,
                'producto_id' => $productId,
                'usuario_id' => $reservation->userId,
                'cantidad' => $reservation->quantity,
                'estado' => $reservation->status->value,
                'expira_en' => $reservation->expiresAt,
                'created_at' => $reservation->reservedAt,
                'updated_at' => $reservation->reservedAt,
            ]);
        });
    }

    /** Cancel the reserva only when it is an active, not expired one of that cliente. */
    public function cancel(NegocioId $negocioId, string $userId, ReservationId $id, DateTimeImmutable $now): bool
    {
        return DB::table('reservas')
            ->where('negocio_id', $negocioId->value)
            ->where('usuario_id', $userId)
            ->where('id', $id->value)
            ->where('estado', ReservationStatus::Active->value)
            ->where('expira_en', '>', $now)
            ->update(['estado' => ReservationStatus::Cancelled->value, 'updated_at' => $now]) > 0;
    }

    /** Read the active reservas of the cliente with the name and the current price of each product. */
    public function activeOf(NegocioId $negocioId, string $userId, DateTimeImmutable $now): array
    {
        $rows = DB::table('reservas')
            ->join('productos', 'productos.id', '=', 'reservas.producto_id')
            ->where('reservas.negocio_id', $negocioId->value)
            ->where('reservas.usuario_id', $userId)
            ->where('reservas.estado', ReservationStatus::Active->value)
            ->where('reservas.expira_en', '>', $now)
            ->orderBy('reservas.created_at')
            ->orderBy('reservas.id')
            ->get(['reservas.id', 'reservas.producto_id', 'productos.nombre', 'reservas.cantidad', 'productos.precio_venta', 'reservas.expira_en']);

        $details = [];

        foreach ($rows as $row) {
            $details[] = new ReservationDetails(
                (string) $row->id,
                (string) $row->producto_id,
                (string) $row->nombre,
                (int) $row->cantidad,
                Money::fromDecimal((string) $row->precio_venta),
                new DateTimeImmutable((string) $row->expira_en),
            );
        }

        return $details;
    }
}
