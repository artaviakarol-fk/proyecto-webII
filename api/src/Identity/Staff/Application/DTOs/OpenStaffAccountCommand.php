<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Application\DTOs;

final readonly class OpenStaffAccountCommand
{
    /**
     * The person, the temporary password the administrator gives and the role: a default one by its name ("cajero")
     * or a role of the negocio by its id.
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $temporaryPassword,
        public string $role,
    ) {}
}
