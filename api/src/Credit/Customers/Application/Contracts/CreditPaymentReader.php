<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\Contracts;

use Src\Credit\Customers\Application\DTOs\CreditPaymentReceipt;
use Src\Credit\Customers\Domain\ValueObjects\CreditPaymentId;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

interface CreditPaymentReader
{
    /** The receipt of an abono of the negocio. */
    public function find(CreditPaymentId $id, NegocioId $negocioId): ?CreditPaymentReceipt;

    /**
     * One page of the abonos of a customer, the newest first.
     *
     * @return Page<CreditPaymentReceipt>
     */
    public function listForCustomer(CustomerId $customerId, NegocioId $negocioId, PageRequest $page): Page;
}
