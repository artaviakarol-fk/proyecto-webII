<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Products\Application;

use PHPUnit\Framework\TestCase;
use Src\Inventory\Products\Application\DTOs\ProductData;
use Src\Inventory\Products\Application\UseCases\CreateProduct;
use Src\Inventory\Products\Application\UseCases\DeleteProduct;
use Src\Inventory\Products\Application\UseCases\GetProduct;
use Src\Inventory\Products\Application\UseCases\ListProducts;
use Src\Inventory\Products\Application\UseCases\UpdateProduct;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\Exceptions\ProductCodeAlreadyInUseException;
use Src\Inventory\Products\Domain\Exceptions\ProductNotFoundException;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Tests\Support\Inventory\InMemoryProductRepository;
use Tests\Support\Shared\FixedNegocioContext;

final class ProductUseCasesTest extends TestCase
{
    private const string NEGOCIO_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string OTHER_NEGOCIO_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d33';

    private InMemoryProductRepository $products;

    private FixedNegocioContext $context;

    /** Wire the use cases to an in-memory repository and a fixed negocio. */
    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository;
        $this->context = new FixedNegocioContext(new NegocioId(self::NEGOCIO_ID));
    }

    /** A new product is stored for the negocio of the account and its first prices go to the history. */
    public function test_creates_a_product_for_the_negocio(): void
    {
        $product = new CreateProduct($this->context, $this->products)->execute($this->data());

        $stored = new GetProduct($this->context, $this->products)->execute($product->id->value);

        $this->assertSame('ARROZ-1', $stored->details->code->value);
        $this->assertSame(150_000, $stored->details->salePrice->cents);
        $this->assertSame(self::NEGOCIO_ID, $stored->negocioId->value);
        $this->assertSame([[$product->id->value, 'creacion']], $this->products->priceHistory);
    }

    /** Two products of the same negocio cannot share a code, and invalid data stores nothing. */
    public function test_rejects_a_repeated_code_and_invalid_data(): void
    {
        $create = new CreateProduct($this->context, $this->products);
        $create->execute($this->data());

        try {
            $create->execute($this->data(salePrice: '0'));
            $this->fail('A sale price of zero was accepted.');
        } catch (InvalidValueException) {
            $this->assertSame(1, $this->list()->total);
        }

        $this->expectException(ProductCodeAlreadyInUseException::class);
        $create->execute($this->data(name: 'Otro arroz'));
    }

    /** Editing a product changes its details and records the new price with the reason. */
    public function test_updates_a_product_and_records_the_price_change(): void
    {
        $product = new CreateProduct($this->context, $this->products)->execute($this->data());

        $updated = new UpdateProduct($this->context, $this->products)->execute($product->id->value, $this->data(name: 'Arroz 2 kg', salePrice: '2800'));

        $this->assertSame('Arroz 2 kg', $updated->details->name->value);
        $this->assertSame([$product->id->value, 'edicion'], $this->products->priceHistory[1]);
    }

    /** The listing searches by name or code, by pages, and only inside the negocio. */
    public function test_lists_the_catalog_of_the_negocio_by_pages(): void
    {
        $create = new CreateProduct($this->context, $this->products);
        $create->execute($this->data('ARROZ-1', 'Arroz 1 kg'));
        $create->execute($this->data('FRIJOL-1', 'Frijoles negros'));
        $create->execute($this->data('CAFE-1', 'Café molido'));
        new CreateProduct(new FixedNegocioContext(new NegocioId(self::OTHER_NEGOCIO_ID)), $this->products)->execute($this->data('ARROZ-9', 'Arroz de otro'));

        $firstPage = $this->list(page: new PageRequest(1, 2));

        $this->assertSame(3, $firstPage->total);
        $this->assertSame(2, $firstPage->lastPage());
        $this->assertSame(['Arroz 1 kg', 'Café molido'], array_map(fn (Product $product): string => $product->details->name->value, $firstPage->items));
        $this->assertSame(1, $this->list('frijol')->total);
    }

    /** A product of another negocio, or a deleted one, is reported as missing. */
    public function test_another_negocio_or_a_deleted_product_is_missing(): void
    {
        $product = new CreateProduct($this->context, $this->products)->execute($this->data());
        $foreign = new GetProduct(new FixedNegocioContext(new NegocioId(self::OTHER_NEGOCIO_ID)), $this->products);

        try {
            $foreign->execute($product->id->value);
            $this->fail('A product of another negocio was found.');
        } catch (ProductNotFoundException) {
            new DeleteProduct($this->context, $this->products)->execute($product->id->value);
        }

        $this->expectException(ProductNotFoundException::class);
        new GetProduct($this->context, $this->products)->execute($product->id->value);
    }

    /** The data of a valid product, with optional overrides. */
    private function data(string $code = 'ARROZ-1', string $name = 'Arroz 1 kg', string $salePrice = '1500'): ProductData
    {
        return new ProductData($code, $name, null, $salePrice, '1100', 10, false, false);
    }

    /**
     * One page of the catalog of the negocio.
     *
     * @return Page<Product>
     */
    private function list(?string $term = null, PageRequest $page = new PageRequest): Page
    {
        return new ListProducts($this->context, $this->products)->execute($term, $page);
    }
}
