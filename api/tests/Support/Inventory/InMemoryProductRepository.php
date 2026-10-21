<?php

declare(strict_types=1);

namespace Tests\Support\Inventory;

use Src\Inventory\Products\Domain\Contracts\ProductRepository;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\Exceptions\ProductCodeAlreadyInUseException;
use Src\Inventory\Products\Domain\ValueObjects\PriceChangeReason;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Tests\Support\Shared\Pages;

final class InMemoryProductRepository implements ProductRepository
{
    /** @var array<string, Product> */
    private array $products = [];

    /** @var list<array{string, string}> Product id and reason of each save that changed the cost or the price (`creacion` for a new one). */
    public array $priceHistory = [];

    private int $sequence = 0;

    /** Generate a predictable identity. */
    public function nextId(): ProductId
    {
        return new ProductId(sprintf('0192f0c4-0000-7000-8000-%012d', ++$this->sequence));
    }

    /** Store the product enforcing a unique code per negocio, and record the price changes. */
    public function save(Product $product, PriceChangeReason $reason = PriceChangeReason::Edited): void
    {
        foreach ($this->products as $stored) {
            $sameNegocio = $stored->negocioId->equals($product->negocioId);
            $sameCode = $stored->details->code->value === $product->details->code->value;

            if ($sameNegocio && $sameCode && ! $stored->id->equals($product->id)) {
                throw ProductCodeAlreadyInUseException::create();
            }
        }

        $before = $this->products[$product->id->value] ?? null;
        $priceChanged = $before === null
            || $before->details->salePrice->cents !== $product->details->salePrice->cents
            || $before->details->currentCost->cents !== $product->details->currentCost->cents;

        if ($priceChanged) {
            $this->priceHistory[] = [$product->id->value, $before === null ? 'creacion' : $reason->value];
        }

        $this->products[$product->id->value] = clone $product;
    }

    /** Find a product scoped to its negocio. */
    public function find(ProductId $id, NegocioId $negocioId): ?Product
    {
        $product = $this->products[$id->value] ?? null;

        return $product?->negocioId->equals($negocioId) === true ? clone $product : null;
    }

    /**
     * One page of the products of the negocio ordered by name, optionally matching a name or code.
     *
     * @return Page<Product>
     */
    public function search(NegocioId $negocioId, ?string $term, PageRequest $page): Page
    {
        $matches = array_filter(
            $this->products,
            fn (Product $product): bool => $product->negocioId->equals($negocioId) && ($term === null
                || mb_stripos($product->details->name->value, $term) !== false
                || mb_stripos($product->details->code->value, $term) !== false),
        );

        usort($matches, fn (Product $a, Product $b): int => [$a->details->name->value, $a->id->value] <=> [$b->details->name->value, $b->id->value]);

        return Pages::of(array_values($matches), $page);
    }

    /** Take the product out of the catalog. */
    public function delete(Product $product): void
    {
        unset($this->products[$product->id->value]);
    }
}
