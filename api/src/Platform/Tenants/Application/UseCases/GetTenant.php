<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Application\UseCases;

use DateTimeImmutable;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionTerms;
use Src\Platform\Tenants\Application\Contracts\TenantDirectory;
use Src\Platform\Tenants\Application\DTOs\TenantSummary;
use Src\Platform\Tenants\Domain\Exceptions\TenantNotFoundException;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class GetTenant
{
    /** Receive the directory of the platform and the terms of the rent. */
    public function __construct(
        private TenantDirectory $directory,
        private SubscriptionTerms $terms,
    ) {}

    /** One negocio of the platform, with its administrators. */
    public function execute(string $negocioId, DateTimeImmutable $now): TenantSummary
    {
        $tenant = $this->directory->find(new NegocioId($negocioId)) ?? throw TenantNotFoundException::create();

        return $tenant->withService($now, $this->terms);
    }
}
