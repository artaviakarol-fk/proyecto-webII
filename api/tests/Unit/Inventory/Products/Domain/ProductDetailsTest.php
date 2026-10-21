<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Products\Domain;

use PHPUnit\Framework\TestCase;
use Src\Inventory\Products\Domain\ValueObjects\ProductCode;
use Src\Inventory\Products\Domain\ValueObjects\ProductDetails;
use Src\Inventory\Products\Domain\ValueObjects\ProductName;
use Src\Inventory\Products\Domain\ValueObjects\Stock;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;

final class ProductDetailsTest extends TestCase
{
    /** A product is never sold for free. */
    public function test_sale_price_of_zero_is_rejected(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->details('0');
    }

    /** Stock is never negative. */
    public function test_negative_stock_is_rejected(): void
    {
        $this->expectException(InvalidValueException::class);

        new Stock(-1);
    }

    /** Codes allow the characters of barcodes and internal codes only. */
    public function test_code_with_spaces_is_rejected(): void
    {
        $this->expectException(InvalidValueException::class);

        new ProductCode('ARROZ 1KG');
    }

    /** A valid product keeps its data. */
    public function test_valid_details_are_kept(): void
    {
        $details = $this->details('1350.50');

        $this->assertSame(135050, $details->salePrice->cents);
        $this->assertSame('Arroz 1 kg', $details->name->value);
    }

    /** Build the details of a product with a sale price. */
    private function details(string $salePrice): ProductDetails
    {
        return new ProductDetails(
            new ProductCode('7441001100011'),
            new ProductName(' Arroz 1 kg '),
            null,
            Money::fromDecimal($salePrice),
            Money::fromDecimal('1000'),
            new Stock(10),
            false,
            false,
        );
    }
}
