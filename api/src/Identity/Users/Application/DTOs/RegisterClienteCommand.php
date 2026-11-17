<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\DTOs;

use SensitiveParameter;

final readonly class RegisterClienteCommand
{
    /** Carry the negocio where the person registers and the data of the new account. */
    public function __construct(
        public string $negocioId,
        public string $name,
        public string $email,
        #[SensitiveParameter] public string $password,
    ) {}
}
