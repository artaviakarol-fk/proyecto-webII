<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Domain\ValueObjects;

/** What to do about one product: how fast it sells, how long it lasts, how urgent it is and how many units to order. */
final readonly class RestockAdvice
{
    /** The days left is none when the product did not sell, so there is no pace to measure it by. */
    public function __construct(
        public float $averagePerDay,
        public ?float $daysLeft,
        public RestockStatus $status,
        public int $suggested,
    ) {}
}
