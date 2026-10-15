<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Domain\Entities;

use Src\Inventory\Products\Domain\ValueObjects\ProductDetails;
use Src\Inventory\Products\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\NegocioId;

final class Product
{
    /** Hold the full state of a product of the catalog. */
    private function __construct(
        public readonly ProductId $id,
        public readonly NegocioId $negocioId,
        public private(set) ProductDetails $details,
        public readonly ?string $photoPath,
    ) {}

    /** Register a new product in the catalog of a negocio. */
    public static function create(ProductId $id, NegocioId $negocioId, ProductDetails $details): self
    {
        return new self($id, $negocioId, $details, null);
    }

    /** Rebuild a product from its stored state. */
    public static function reconstitute(ProductId $id, NegocioId $negocioId, ProductDetails $details, ?string $photoPath): self
    {
        return new self($id, $negocioId, $details, $photoPath);
    }

    /** Replace the editable data of the product. */
    public function revise(ProductDetails $details): void
    {
        $this->details = $details;
    }

    /** Tell whether no unit is left to sell. */
    public function isSoldOut(): bool
    {
        return $this->details->stock->isEmpty();
    }
}
