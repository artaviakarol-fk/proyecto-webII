<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Application\DTOs;

/** What the AI understood from the text, already checked against the schema; nothing of it is trusted yet. */
final readonly class InterpretedSale
{
    /**
     * Carry the customer and the way of paying the text mentions, if any, and the products.
     *
     * @param  list<InterpretedItem>  $items
     */
    public function __construct(
        public ?string $customer,
        public ?string $paymentMethod,
        public array $items,
    ) {}
}
