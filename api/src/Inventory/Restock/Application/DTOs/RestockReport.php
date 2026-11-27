<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Application\DTOs;

/** The products of a negocio from the most urgent to restock to the least, and the ones that sold the most. */
final readonly class RestockReport
{
    /**
     * @param  list<RestockItem>  $items  Most urgent first.
     * @param  list<RestockItem>  $topSellers  The best sellers, most sold first.
     */
    public function __construct(
        public int $windowDays,
        public array $items,
        public array $topSellers,
    ) {}
}
