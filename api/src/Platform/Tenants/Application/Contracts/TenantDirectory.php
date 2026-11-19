<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Application\Contracts;

use Src\Platform\Tenants\Application\DTOs\TenantSummary;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

interface TenantDirectory
{
    /**
     * One page of the negocios of the platform, the newest first, matching a part of the name when given.
     *
     * @return Page<TenantSummary>
     */
    public function list(?string $term, PageRequest $page): Page;

    /** One negocio of the platform. */
    public function find(NegocioId $id): ?TenantSummary;
}
