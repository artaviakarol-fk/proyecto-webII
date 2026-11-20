<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Application\UseCases;

use Src\Platform\Tenants\Application\Contracts\TenantDirectory;
use Src\Platform\Tenants\Application\DTOs\TenantSummary;
use Src\Platform\Tenants\Domain\Exceptions\TenantNotFoundException;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class GetTenant
{
    /** Receive the directory of the platform. */
    public function __construct(private TenantDirectory $directory) {}

    /** One negocio of the platform, with its administrators. */
    public function execute(string $negocioId): TenantSummary
    {
        return $this->directory->find(new NegocioId($negocioId)) ?? throw TenantNotFoundException::create();
    }
}
