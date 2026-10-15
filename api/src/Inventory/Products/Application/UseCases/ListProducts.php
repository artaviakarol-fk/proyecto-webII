<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Domain\Contracts\ProductRepository;
use Src\Inventory\Products\Domain\ValueObjects\ProductPage;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class ListProducts
{
    /** Receive the negocio of the operation and the product repository. */
    public function __construct(
        private NegocioContext $context,
        private ProductRepository $products,
    ) {}

    /** One page of the catalog of the current negocio, optionally searching by name or code. */
    public function execute(?string $term, int $page, int $perPage): ProductPage
    {
        $term = $term === null || trim($term) === '' ? null : trim($term);

        return $this->products->search($this->context->current(), $term, $page, $perPage);
    }
}
