<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Contracts;

interface DatabaseStatusChecker
{
    /** Tell whether the database answers a query right now. */
    public function isAvailable(): bool;
}
