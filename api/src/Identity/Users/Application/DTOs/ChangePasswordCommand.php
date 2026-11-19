<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\DTOs;

final readonly class ChangePasswordCommand
{
    /** The password the account has now, which proves who asks, and the one it wants. */
    public function __construct(
        public string $currentPassword,
        public string $newPassword,
    ) {}
}
