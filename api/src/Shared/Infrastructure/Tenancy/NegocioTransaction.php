<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;

final readonly class NegocioTransaction
{
    public const string SETTING = 'app.negocio_id';

    /**
     * Run the operation in a database transaction that only reaches the rows of one negocio; null reaches none.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    public function run(?string $negocioId, Closure $operation): mixed
    {
        return DB::transaction(function () use ($negocioId, $operation): mixed {
            $this->scope($negocioId);

            return $operation();
        });
    }

    /** Set the negocio of the open transaction; the value disappears when the transaction ends. */
    public function scope(?string $negocioId): void
    {
        DB::statement('select set_config(?, ?, true)', [self::SETTING, $negocioId ?? '']);
    }
}
