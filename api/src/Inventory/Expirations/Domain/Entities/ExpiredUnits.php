<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Domain\Entities;

use DateTimeImmutable;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiredQuantity;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiredUnitsId;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiredUnitsStatus;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiryReason;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class ExpiredUnits
{
    /** Hold units of a product found expired: how many, why, who marked them, when and whether they are still on the shelf. */
    private function __construct(
        public ExpiredUnitsId $id,
        public NegocioId $negocioId,
        public ProductId $productId,
        public ExpiredQuantity $quantity,
        public ExpiryReason $reason,
        public string $markedBy,
        public DateTimeImmutable $markedAt,
        public ExpiredUnitsStatus $status,
    ) {}

    /** Mark units of a product as expired; they start on the shelf, out of the sellable stock. */
    public static function mark(
        ExpiredUnitsId $id,
        NegocioId $negocioId,
        ProductId $productId,
        ExpiredQuantity $quantity,
        ExpiryReason $reason,
        string $markedBy,
        DateTimeImmutable $markedAt,
    ): self {
        return new self($id, $negocioId, $productId, $quantity, $reason, $markedBy, $markedAt, ExpiredUnitsStatus::Active);
    }
}
