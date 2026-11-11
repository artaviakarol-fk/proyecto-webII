<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\DTOs;

final readonly class CustomerData
{
    /**
     * Carry the editable data of a customer as the request sends it. The limit is a decimal string,
     * or null to use the base limit of the negocio. The balance is never part of it.
     */
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $creditLimit,
        public bool $active,
    ) {}
}
