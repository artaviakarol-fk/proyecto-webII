<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Src\Shared\Domain\Contracts\TransactionRunner;

final class ImmediateTransactionRunner implements TransactionRunner
{
    /**
     * Run the callback at once, without a database: the unit tests have none.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}
