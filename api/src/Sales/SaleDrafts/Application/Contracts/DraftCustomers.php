<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Application\Contracts;

use DateTimeImmutable;
use Src\Sales\SaleDrafts\Application\DTOs\DraftCustomer;

interface DraftCustomers
{
    /**
     * The customers of fiado of the current negocio, with what they can buy on fiado at a moment.
     *
     * @return list<DraftCustomer>
     */
    public function all(DateTimeImmutable $at): array;
}
