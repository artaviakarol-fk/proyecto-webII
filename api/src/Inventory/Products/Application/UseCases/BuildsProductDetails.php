<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Application\UseCases;

use Src\Inventory\Products\Application\DTOs\ProductData;
use Src\Inventory\Products\Domain\ValueObjects\Category;
use Src\Inventory\Products\Domain\ValueObjects\ProductCode;
use Src\Inventory\Products\Domain\ValueObjects\ProductDetails;
use Src\Inventory\Products\Domain\ValueObjects\ProductName;
use Src\Inventory\Products\Domain\ValueObjects\Stock;
use Src\Inventory\Shared\Domain\ValueObjects\Money;

trait BuildsProductDetails
{
    /** Turn the raw data into validated value objects. */
    private function details(ProductData $data): ProductDetails
    {
        return new ProductDetails(
            new ProductCode($data->code),
            new ProductName($data->name),
            $data->category === null || trim($data->category) === '' ? null : new Category($data->category),
            Money::fromDecimal($data->salePrice),
            Money::fromDecimal($data->currentCost),
            new Stock($data->stock),
            $data->perishable,
            $data->liquor,
        );
    }
}
