<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Domain\Contracts\DatabaseStatusChecker;
use Throwable;

final readonly class DatabaseConnectionStatusChecker implements DatabaseStatusChecker
{
    /** Receive the default database connection. */
    public function __construct(private ConnectionInterface $connection) {}

    /** Run a trivial query and report whether it succeeded. */
    public function isAvailable(): bool
    {
        try {
            $this->connection->select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
