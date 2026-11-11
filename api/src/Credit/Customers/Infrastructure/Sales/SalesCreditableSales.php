<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Sales;

use Src\Credit\Customers\Application\Contracts\CreditableSales;
use Src\Credit\Customers\Application\DTOs\CreditableSale;
use Src\Sales\Sales\Application\UseCases\GetSale;
use Src\Sales\Sales\Application\UseCases\PaySale;
use Src\Sales\Sales\Domain\Entities\Sale;
use Src\Sales\Sales\Domain\Exceptions\SaleNotFoundException;
use Src\Sales\Sales\Domain\ValueObjects\SaleStatus;
use Src\Shared\Domain\ValueObjects\Money;

/** Lets the fiado read and close a sale through the use cases of the sales, so the credit never imports the sales domain. */
final readonly class SalesCreditableSales implements CreditableSales
{
    /** Receive the use cases that read and close a sale of the current negocio. */
    public function __construct(private GetSale $getSale, private PaySale $paySale) {}

    /** Read the sale; one of another negocio is not found. */
    public function find(string $saleId): ?CreditableSale
    {
        try {
            $sale = $this->getSale->execute($saleId);
        } catch (SaleNotFoundException) {
            return null;
        }

        return new CreditableSale($saleId, Money::fromDecimal($sale->total), $sale->status === SaleStatus::Open->value);
    }

    /** Close the sale on fiado through the use case of the sales, which locks it and refuses one that is not open. */
    public function markOnFiado(string $saleId): void
    {
        $this->paySale->execute($saleId, Sale::FIADO);
    }
}
