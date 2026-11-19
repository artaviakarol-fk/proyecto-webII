<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\NegocioId;

/** Whether the platform is serving a negocio now; a negocio that did not pay its rent is not served. */
interface NegocioServiceAccess
{
    /** Whether the staff and the clientes of the negocio can use it now. */
    public function serves(NegocioId $negocioId): bool;
}
