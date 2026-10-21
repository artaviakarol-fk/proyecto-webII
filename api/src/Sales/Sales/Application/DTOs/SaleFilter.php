<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\DTOs;

use DateTimeImmutable;
use Src\Sales\Sales\Domain\ValueObjects\SaleStatus;

final readonly class SaleFilter
{
    /** Narrow the listing to one status and to the sales opened on one day of the negocio; null keeps them all. */
    public function __construct(
        public ?SaleStatus $status = null,
        public ?DateTimeImmutable $day = null,
    ) {}
}
