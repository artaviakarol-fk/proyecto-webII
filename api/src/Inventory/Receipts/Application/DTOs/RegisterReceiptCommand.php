<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Application\DTOs;

use DateTimeImmutable;

final readonly class RegisterReceiptCommand
{
    /**
     * Carry a delivery as the request sends it and the moment it is registered.
     *
     * @param  list<ReceiptLineData>  $lines
     */
    public function __construct(
        public string $supplier,
        public string $date,
        public array $lines,
        public DateTimeImmutable $now,
    ) {}
}
