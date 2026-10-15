<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use Src\Shared\Domain\ValueObjects\NegocioId;

/** Every negocio keeps its files in its own folder: negocios/{id}/... */
final class ImageFolders
{
    /**
     * Folder of the photo of one product: negocios/{id}/productos/{producto}.
     *
     * Each product has its own folder because the file is named after the hash of its content:
     * two products with the same image would otherwise share one file, and replacing the photo of
     * one would delete the photo the other still uses.
     */
    public static function product(NegocioId $negocioId, string $productId): string
    {
        return "negocios/{$negocioId->value}/productos/{$productId}";
    }

    /** Folder of the logo of a negocio. */
    public static function logo(NegocioId $negocioId): string
    {
        return "negocios/{$negocioId->value}/logo";
    }
}
