<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Src\Shared\Domain\Contracts\DatabaseStatusChecker;

final readonly class FakeDatabaseStatusChecker implements DatabaseStatusChecker
{
    /** Answer the given availability without touching a database. */
    public function __construct(private bool $available) {}

    public function isAvailable(): bool
    {
        return $this->available;
    }
}
