<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Application\DTOs;

final readonly class InterpretedItem
{
    /** A product as the cashier named it and how many units. */
    public function __construct(
        public string $product,
        public int $quantity,
    ) {}
}
