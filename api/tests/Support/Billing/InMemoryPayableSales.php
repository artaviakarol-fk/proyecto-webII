<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use Src\Billing\Payments\Application\Contracts\PayableSales;
use Src\Billing\Payments\Application\DTOs\PayableSale;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;

final class InMemoryPayableSales implements PayableSales
{
    /** @var array<string, PayableSale> */
    private array $sales = [];

    /** @var array<string, string> The way of paying of each sale closed as paid. */
    public array $paid = [];

    /** @var list<string> The sales cancelled after a refund. */
    public array $cancelled = [];

    /** Keep a sale of the current negocio. */
    public function add(PayableSale $sale): void
    {
        $this->sales[$sale->id->value] = $sale;
    }

    /** Find a sale of the current negocio. */
    public function find(SaleId $saleId): ?PayableSale
    {
        return $this->sales[$saleId->value] ?? null;
    }

    /** Close the sale as paid. */
    public function markPaid(SaleId $saleId, PaymentMethod $method): void
    {
        $sale = $this->sales[$saleId->value];
        $this->sales[$saleId->value] = new PayableSale($sale->id, $sale->total, false);
        $this->paid[$saleId->value] = $method->value;
    }

    /** Cancel the sale. */
    public function cancel(SaleId $saleId): void
    {
        $this->cancelled[] = $saleId->value;
    }
}
