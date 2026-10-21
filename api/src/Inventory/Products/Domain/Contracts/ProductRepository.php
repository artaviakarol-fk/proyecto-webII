<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Domain\Contracts;

use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\Exceptions\ProductCodeAlreadyInUseException;
use Src\Inventory\Products\Domain\ValueObjects\PriceChangeReason;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

interface ProductRepository
{
    /** Generate the identity for a new product. */
    public function nextId(): ProductId;

    /**
     * Persist a new or modified product; when its cost or sale price changes, the price history
     * records the new values and the reason (a new product is recorded as created).
     *
     * @throws ProductCodeAlreadyInUseException
     */
    public function save(Product $product, PriceChangeReason $reason = PriceChangeReason::Edited): void;

    /** Find a live product of the negocio. */
    public function find(ProductId $id, NegocioId $negocioId): ?Product;

    /**
     * One page of the live products of the negocio ordered by name, optionally matching a name or code.
     *
     * @return Page<Product>
     */
    public function search(NegocioId $negocioId, ?string $term, PageRequest $page): Page;

    /** Remove the product from the catalog, keeping its history (soft delete). */
    public function delete(Product $product): void;
}
