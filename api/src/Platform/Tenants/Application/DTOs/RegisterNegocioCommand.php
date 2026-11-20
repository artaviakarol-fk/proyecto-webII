<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Application\DTOs;

final readonly class RegisterNegocioCommand
{
    /** The name of the new negocio and its first administrator, with the temporary password the platform gives. */
    public function __construct(
        public string $negocioName,
        public string $administratorName,
        public string $administratorEmail,
        public string $temporaryPassword,
    ) {}
}
