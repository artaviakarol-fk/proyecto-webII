<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Application\UseCases;

use Src\Negocios\Profile\Domain\Contracts\NegocioRepository;
use Src\Negocios\Profile\Domain\Entities\Negocio;
use Src\Negocios\Profile\Domain\Exceptions\NegocioNotFoundException;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class GetCurrentNegocio
{
    /** Receive the negocio of the current operation and the repository that stores the negocios. */
    public function __construct(
        private NegocioContext $context,
        private NegocioRepository $negocios,
    ) {}

    /** Answer the negocio the authenticated account belongs to. */
    public function execute(): Negocio
    {
        return $this->negocios->find($this->context->current()) ?? throw NegocioNotFoundException::create();
    }
}
