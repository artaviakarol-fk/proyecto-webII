<?php

namespace App\Infrastructure\Health;

use App\Application\Health\DatabaseStatusChecker;
use Illuminate\Database\ConnectionInterface;
use Throwable;

/**
 * Adaptador: verifica la base de datos con una consulta simple.
 */
final readonly class EloquentDatabaseStatusChecker implements DatabaseStatusChecker
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

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
