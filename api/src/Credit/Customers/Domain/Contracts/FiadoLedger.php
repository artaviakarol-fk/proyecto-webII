<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Contracts;

use Src\Credit\Customers\Domain\ValueObjects\FiadoCharge;
use Src\Shared\Domain\ValueObjects\NegocioId;

/** The book of movements of fiado: rows are only added, and each one moves the balance of its customer. */
interface FiadoLedger
{
    /** Record that a sale was charged to a customer; the balance grows by its amount. */
    public function recordCharge(NegocioId $negocioId, FiadoCharge $charge, string $userId): void;

    /** The charge a sale left, if it was sold on fiado and was not cancelled yet. */
    public function pendingChargeOf(NegocioId $negocioId, string $saleId): ?FiadoCharge;

    /** Record that the charge of a cancelled sale is given back; the balance goes down by its amount. */
    public function recordCancellation(NegocioId $negocioId, FiadoCharge $charge, string $userId): void;
}
