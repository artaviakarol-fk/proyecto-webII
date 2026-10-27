<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\Contracts;

use Src\Billing\Payments\Application\DTOs\PayableSale;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;

interface PayableSales
{
    /** Find a sale of the current negocio with what a payment needs to know about it; one of another negocio is not found. */
    public function find(SaleId $saleId): ?PayableSale;

    /** Close the sale as paid with the way of paying that was used; it joins the transaction that is running. */
    public function markPaid(SaleId $saleId, PaymentMethod $method): void;

    /** Cancel the sale of a payment that was given back, returning its units to the stock; it joins the running transaction. */
    public function cancel(SaleId $saleId): void;
}
