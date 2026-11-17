<?php

declare(strict_types=1);

namespace Src\Sales\Purchases\Application\Contracts;

use DateTimeImmutable;
use Src\Sales\Sales\Application\DTOs\SaleLineData;

interface CartReservations
{
    /**
     * Take the reservas that the cliente of the request still holds to buy them: they stop holding units and become the
     * lines of the sale. Empty when the cliente holds none.
     *
     * @return list<SaleLineData>
     */
    public function claim(DateTimeImmutable $now): array;
}
