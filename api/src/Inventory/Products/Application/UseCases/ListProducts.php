<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Domain\Contracts\ProductRepository;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

final readonly class ListProducts
{
    /** Receive the negocio of the operation and the product repository. */
    public function __construct(
        private NegocioContext $context,
        private ProductRepository $products,
    ) {}

    /**
     * One page of the catalog of the current negocio, optionally searching by name or code and only of a category.
     *
     * @return Page<Product>
     */
    public function execute(?string $term, PageRequest $page, ?string $category = null): Page
    {
        $term = $term === null || trim($term) === '' ? null : trim($term);
        $category = $category === null || trim($category) === '' ? null : trim($category);

        return $this->products->search($this->context->current(), $term, $page, $category);
    }

    /**
     * The categories of the products of the current negocio, to filter by them.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        return $this->products->categories($this->context->current());
    }
}
