<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\ValueObjects\NegocioId;

/** The negocio of an operation that has no account behind it, such as the notification of a provider. */
final readonly class FixedNegocioContext implements NegocioContext
{
    /** Hold the negocio that the route chose. */
    public function __construct(private NegocioId $negocioId) {}

    /** Answer the negocio that was fixed. */
    public function current(): NegocioId
    {
        return $this->negocioId;
    }
}
