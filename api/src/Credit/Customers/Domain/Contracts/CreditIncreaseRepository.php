<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Contracts;

use Src\Credit\Customers\Domain\Entities\CreditIncrease;
use Src\Credit\Customers\Domain\ValueObjects\CreditIncreaseId;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface CreditIncreaseRepository
{
    /** Generate the identity for a new increase. */
    public function nextId(): CreditIncreaseId;

    /** Persist a new increase or its reversion. */
    public function save(CreditIncrease $increase): void;

    /** Find an increase of the negocio and lock its row until the transaction ends. */
    public function findForUpdate(CreditIncreaseId $id, NegocioId $negocioId): ?CreditIncrease;
}
