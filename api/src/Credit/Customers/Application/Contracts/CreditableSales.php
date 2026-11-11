<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\Contracts;

use Src\Credit\Customers\Application\DTOs\CreditableSale;

interface CreditableSales
{
    /** Find a sale of the current negocio with what the fiado needs to know about it; one of another negocio is not found. */
    public function find(string $saleId): ?CreditableSale;

    /** Close the open sale on fiado; it joins the transaction that is running and refuses a sale that is no longer open. */
    public function markOnFiado(string $saleId): void;
}
