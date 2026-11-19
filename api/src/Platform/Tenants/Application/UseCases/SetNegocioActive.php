<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Application\UseCases;

use DateTimeImmutable;
use Src\Negocios\Profile\Domain\Contracts\NegocioRepository;
use Src\Platform\Tenants\Application\DTOs\TenantSummary;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class SetNegocioActive
{
    /** Receive the negocios and the reading of one negocio of the platform. */
    public function __construct(
        private NegocioRepository $negocios,
        private GetTenant $getTenant,
    ) {}

    /**
     * Turn a negocio off or on again. A negocio turned off is suspended whatever date it paid until: its staff and its
     * clientes cannot use it, and its administrators can only see and pay. Its data is kept.
     */
    public function execute(string $negocioId, bool $active, DateTimeImmutable $now): TenantSummary
    {
        $tenant = $this->getTenant->execute($negocioId, $now);
        $this->negocios->setActive(new NegocioId($tenant->id), $active);

        return $this->getTenant->execute($negocioId, $now);
    }
}
