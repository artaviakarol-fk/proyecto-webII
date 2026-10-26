<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use Src\Billing\Payments\Application\Contracts\PayableSales;
use Src\Billing\Payments\Application\DTOs\PayableSale;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;

final class InMemoryPayableSales implements PayableSales
{
    /** @var array<string, PayableSale> */
    private array $sales = [];

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
}
