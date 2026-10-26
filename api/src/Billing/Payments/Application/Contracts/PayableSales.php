<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\Contracts;

use Src\Billing\Payments\Application\DTOs\PayableSale;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;

interface PayableSales
{
    /** Find a sale of the current negocio with what a payment needs to know about it; one of another negocio is not found. */
    public function find(SaleId $saleId): ?PayableSale;
}
