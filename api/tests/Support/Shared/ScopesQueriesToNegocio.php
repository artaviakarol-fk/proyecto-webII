<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;

trait ScopesQueriesToNegocio
{
    /** Set the negocio the following queries of the test transaction reach; null reaches none. */
    protected function scopeQueriesTo(?string $negocioId): void
    {
        $this->app->make(NegocioTransaction::class)->scope($negocioId);
    }
}
