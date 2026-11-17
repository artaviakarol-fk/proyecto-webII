<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Domain\Entities;

use DateTimeImmutable;
use Src\Inventory\Reservations\Domain\ValueObjects\ReservationId;
use Src\Inventory\Reservations\Domain\ValueObjects\ReservationStatus;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class Reservation
{
    /** Minutes a cliente has to pay before the units go back to everybody. */
    public const int MINUTES = 15;

    /** Most units of one product a cliente can hold at once. */
    public const int MAX_QUANTITY = 100;

    /** Hold the state of the units of one product that a cliente keeps for a while. */
    private function __construct(
        public ReservationId $id,
        public NegocioId $negocioId,
        public ProductId $productId,
        public string $userId,
        public int $quantity,
        public ReservationStatus $status,
        public DateTimeImmutable $reservedAt,
        public DateTimeImmutable $expiresAt,
    ) {}

    /** Hold units of a product for a cliente, from now and for the minutes a reserva lasts. */
    public static function hold(
        ReservationId $id,
        NegocioId $negocioId,
        ProductId $productId,
        string $userId,
        int $quantity,
        DateTimeImmutable $now,
    ): self {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidValueException('inventory.reservation_quantity_out_of_range', ['max' => self::MAX_QUANTITY]);
        }

        return new self(
            $id,
            $negocioId,
            $productId,
            $userId,
            $quantity,
            ReservationStatus::Active,
            $now,
            $now->modify('+'.self::MINUTES.' minutes'),
        );
    }
}
