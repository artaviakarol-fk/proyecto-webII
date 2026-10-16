<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Domain\Contracts;

use Src\Inventory\Expirations\Domain\Entities\ExpiredUnits;
use Src\Inventory\Expirations\Domain\Exceptions\ExpiredUnitsExceedSellableException;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiredUnitsId;

interface ExpiredUnitsRepository
{
    /** Generate the identity for new expired units. */
    public function nextId(): ExpiredUnitsId;

    /**
     * Store the expired units after locking the product row, so two registers cannot mark the same units
     * twice: a product never has more units marked as expired than units in stock.
     *
     * @throws ExpiredUnitsExceedSellableException
     */
    public function save(ExpiredUnits $units): void;
}
