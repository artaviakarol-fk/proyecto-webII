<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Domain\Entities;

use Src\Shared\Domain\ValueObjects\NegocioId;

final class Negocio
{
    /** Hold the profile of a negocio, the tenant every row of the domain belongs to. */
    private function __construct(
        public readonly NegocioId $id,
        public readonly string $name,
        public private(set) ?string $logoPath,
        public readonly bool $active,
    ) {}

    /** Rebuild a negocio from its stored state. */
    public static function reconstitute(NegocioId $id, string $name, ?string $logoPath, bool $active): self
    {
        return new self($id, $name, $logoPath, $active);
    }

    /** Point the negocio to a new stored logo and answer the path of the one it replaces, if any. */
    public function changeLogo(string $path): ?string
    {
        $previous = $this->logoPath;
        $this->logoPath = $path;

        return $previous === $path ? null : $previous;
    }
}
