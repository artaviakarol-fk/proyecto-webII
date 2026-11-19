<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Application\DTOs;

final readonly class ChangeStaffAccessCommand
{
    /** The new role and whether the account can log in; what is null stays as it is. */
    public function __construct(
        public ?string $role,
        public ?bool $active,
    ) {}
}
