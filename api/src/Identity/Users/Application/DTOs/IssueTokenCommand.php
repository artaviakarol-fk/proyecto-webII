<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\DTOs;

use SensitiveParameter;

final readonly class IssueTokenCommand
{
    /** Carry the credentials and the name of the device that logs in. */
    public function __construct(
        public string $email,
        #[SensitiveParameter] public string $password,
        public string $deviceName,
    ) {}
}
