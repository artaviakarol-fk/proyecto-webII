<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Infrastructure\Credit;

use Src\Credit\Customers\Application\UseCases\ReverseFiadoCharge;
use Src\Sales\Sales\Application\Contracts\FiadoCharges;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;

/** Lets the sales give back a fiado charge through the use case of the credit, so the sales never import its domain. */
final readonly class CreditFiadoCharges implements FiadoCharges
{
    /** Receive the use case of the credit that reverses the charge of a sale. */
    public function __construct(private ReverseFiadoCharge $reverseCharge) {}

    /** Reverse the charge of the sale, if it had one. */
    public function reverse(SaleId $saleId): void
    {
        $this->reverseCharge->execute($saleId->value);
    }
}
