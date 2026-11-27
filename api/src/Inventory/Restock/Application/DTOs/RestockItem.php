<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Application\DTOs;

use Src\Inventory\Restock\Domain\ValueObjects\RestockStatus;

/** A product with what to do about its stock. */
final readonly class RestockItem
{
    /** Receive the product, its figures and the advice. */
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public ?string $category,
        public int $stock,
        public int $sold,
        public float $averagePerDay,
        public ?float $daysLeft,
        public RestockStatus $status,
        public int $suggested,
    ) {}
}
