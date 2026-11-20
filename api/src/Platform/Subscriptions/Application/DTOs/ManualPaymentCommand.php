<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Application\DTOs;

final readonly class ManualPaymentCommand
{
    /** A payment the platform received outside of the system: in cash or by SINPE to its number. */
    public function __construct(
        public string $negocioId,
        public int $months,
        public string $method,
        public ?string $reference,
    ) {}
}
