<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Domain\Entities;

use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class Negocio
{
    /** Hold the profile of a negocio, the tenant every row of the domain belongs to. */
    private function __construct(
        public NegocioId $id,
        public string $name,
        public ?string $logoPath,
        public bool $active,
    ) {}

    /** Rebuild a negocio from its stored state. */
    public static function reconstitute(NegocioId $id, string $name, ?string $logoPath, bool $active): self
    {
        return new self($id, $name, $logoPath, $active);
    }
}
