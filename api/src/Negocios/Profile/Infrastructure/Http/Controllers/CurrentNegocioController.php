<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Infrastructure\Http\Controllers;

use Src\Negocios\Profile\Application\UseCases\GetCurrentNegocio;
use Src\Negocios\Profile\Infrastructure\Http\Resources\NegocioResource;

final readonly class CurrentNegocioController
{
    /** Show the negocio of the authenticated account, whatever negocio the request names. */
    public function __invoke(GetCurrentNegocio $getCurrentNegocio): NegocioResource
    {
        return new NegocioResource($getCurrentNegocio->execute());
    }
}
