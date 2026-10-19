<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class InsufficientStockException extends DomainException
{
    /** Build the exception for a product that has fewer sellable units than the sale asks for. */
    public static function create(string $product, int $available): self
    {
        return new self('sales.insufficient_stock', ['product' => $product, 'available' => $available]);
    }
}
