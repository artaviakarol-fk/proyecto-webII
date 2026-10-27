<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Sales;

use Src\Billing\Payments\Application\Contracts\PayableSales;
use Src\Billing\Payments\Application\DTOs\PayableSale;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Sales\Sales\Application\UseCases\CancelSale;
use Src\Sales\Sales\Application\UseCases\GetSale;
use Src\Sales\Sales\Application\UseCases\PaySale;
use Src\Sales\Sales\Domain\Exceptions\SaleNotFoundException;
use Src\Sales\Sales\Domain\ValueObjects\SaleStatus;
use Src\Shared\Domain\ValueObjects\Money;

/** Lets the payments read a sale through the use case of the sales, so the payments never import the sales domain. */
final readonly class SalesPayableSales implements PayableSales
{
    /** Receive the use case that reads a sale of the current negocio. */
    public function __construct(private GetSale $getSale, private PaySale $paySale, private CancelSale $cancelSale) {}

    /** Read the sale; one of another negocio is not found. */
    public function find(SaleId $saleId): ?PayableSale
    {
        try {
            $sale = $this->getSale->execute($saleId->value);
        } catch (SaleNotFoundException) {
            return null;
        }

        return new PayableSale($saleId, Money::fromDecimal($sale->total), $sale->status === SaleStatus::Open->value);
    }

    /** Close the sale as paid through the use case of the sales. */
    public function markPaid(SaleId $saleId, PaymentMethod $method): void
    {
        $this->paySale->execute($saleId->value, $method->value);
    }

    /** Cancel the sale through the use case of the sales, which gives the units back to the stock. */
    public function cancel(SaleId $saleId): void
    {
        $this->cancelSale->execute($saleId->value);
    }
}
