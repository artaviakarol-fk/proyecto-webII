<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Application\DTOs;

final readonly class TenantAdministrator
{
    /** Who can manage a negocio and whether that account still has its temporary password. */
    public function __construct(
        public string $name,
        public string $email,
        public bool $active,
        public bool $mustChangePassword,
    ) {}
}
