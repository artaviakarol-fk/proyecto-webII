<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\NegocioId;

interface NegocioContext
{
    /** Identify the negocio the current operation belongs to. */
    public function current(): NegocioId;
}
