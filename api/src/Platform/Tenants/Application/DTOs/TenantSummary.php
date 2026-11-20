<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Application\DTOs;

use DateTimeImmutable;

final readonly class TenantSummary
{
    /**
     * What the platform knows of a negocio: its name, whether it is active, when it was opened and its administrators.
     * The data of the negocio itself (sales, customers, fiado, stock) is never part of it.
     *
     * @param  list<TenantAdministrator>  $administrators
     */
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
        public DateTimeImmutable $openedAt,
        public array $administrators,
    ) {}
}
