<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use Src\Shared\Domain\ValueObjects\NegocioId;

/** Every negocio keeps its files in its own folder: negocios/{id}/... */
final class ImageFolders
{
    /** Folder of the product photos of a negocio. */
    public static function products(NegocioId $negocioId): string
    {
        return "negocios/{$negocioId->value}/productos";
    }

    /** Folder of the logo of a negocio. */
    public static function logo(NegocioId $negocioId): string
    {
        return "negocios/{$negocioId->value}/logo";
    }
}
