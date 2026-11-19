<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Application\UseCases;

use DateTimeImmutable;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionTerms;
use Src\Platform\Tenants\Application\Contracts\TenantDirectory;
use Src\Platform\Tenants\Application\DTOs\TenantSummary;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

final readonly class ListTenants
{
    /** Receive the directory of the platform and the terms of the rent. */
    public function __construct(
        private TenantDirectory $directory,
        private SubscriptionTerms $terms,
    ) {}

    /**
     * One page of the negocios of the platform, optionally matching a part of the name.
     *
     * @return Page<TenantSummary>
     */
    public function execute(?string $term, PageRequest $page, DateTimeImmutable $now): Page
    {
        return $this->directory->list($term, $page)->map(fn (TenantSummary $tenant): TenantSummary => $tenant->withService($now, $this->terms));
    }
}
