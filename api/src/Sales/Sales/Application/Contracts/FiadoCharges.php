<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\Contracts;

use Src\Sales\Sales\Domain\ValueObjects\SaleId;

interface FiadoCharges
{
    /** Give back to the customer what a cancelled sale on fiado had charged; it joins the transaction that is running. */
    public function reverse(SaleId $saleId): void;
}
