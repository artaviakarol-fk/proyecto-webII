<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure\Access;

use Carbon\CarbonImmutable;
use Src\Identity\Users\Domain\Contracts\NegocioServiceAccess;
use Src\Platform\Subscriptions\Application\ServiceGate;
use Src\Shared\Domain\ValueObjects\NegocioId;

/** Answers the accounts whether the platform serves their negocio now, from its rent. */
final readonly class GateNegocioServiceAccess implements NegocioServiceAccess
{
    /** Receive the gate of the platform. */
    public function __construct(private ServiceGate $gate) {}

    /** Whether the negocio is served at this moment. */
    public function serves(NegocioId $negocioId): bool
    {
        return $this->gate->status($negocioId, CarbonImmutable::now()->toDateTimeImmutable())->serves();
    }
}
