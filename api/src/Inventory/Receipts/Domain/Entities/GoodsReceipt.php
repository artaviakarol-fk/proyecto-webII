<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Domain\Entities;

use DateTimeImmutable;
use Src\Inventory\Receipts\Domain\ValueObjects\ReceiptId;
use Src\Inventory\Receipts\Domain\ValueObjects\ReceiptLine;
use Src\Inventory\Receipts\Domain\ValueObjects\Supplier;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class GoodsReceipt
{
    /**
     * Hold a delivery of goods: who sent it, the day it arrived, who received it and what came.
     *
     * @param  list<ReceiptLine>  $lines
     */
    private function __construct(
        public ReceiptId $id,
        public NegocioId $negocioId,
        public Supplier $supplier,
        public DateTimeImmutable $date,
        public string $receivedBy,
        public array $lines,
    ) {}

    /**
     * Register a delivery; it needs at least one line and cannot be dated in the future.
     *
     * @param  list<ReceiptLine>  $lines
     */
    public static function register(
        ReceiptId $id,
        NegocioId $negocioId,
        Supplier $supplier,
        DateTimeImmutable $date,
        DateTimeImmutable $today,
        string $receivedBy,
        array $lines,
    ): self {
        if ($lines === []) {
            throw new InvalidValueException('inventory.receipt_without_lines');
        }

        if ($date->format('Y-m-d') > $today->format('Y-m-d')) {
            throw new InvalidValueException('inventory.receipt_in_the_future');
        }

        return new self($id, $negocioId, $supplier, $date, $receivedBy, $lines);
    }
}
