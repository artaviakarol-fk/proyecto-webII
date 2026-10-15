<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Domain\Contracts;

use Src\Negocios\Profile\Domain\Entities\Negocio;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface NegocioRepository
{
    /** Find a negocio by its identifier. */
    public function find(NegocioId $id): ?Negocio;
}
