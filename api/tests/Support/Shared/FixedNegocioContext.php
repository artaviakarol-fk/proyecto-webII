<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class FixedNegocioContext implements NegocioContext
{
    /** Always answer the same negocio, as if its account were authenticated. */
    public function __construct(private NegocioId $negocio) {}

    /** The negocio of every operation. */
    public function current(): NegocioId
    {
        return $this->negocio;
    }
}
