<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Negocios\Profile\Domain\Entities\Negocio;

/**
 * @property-read Negocio $resource
 */
final class NegocioResource extends JsonResource
{
    /** Wrap the profile of a negocio. */
    public function __construct(private readonly Negocio $negocio)
    {
        parent::__construct($negocio);
    }

    /**
     * Expose the public profile of the negocio.
     *
     * @return array<string, string|bool|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->negocio->id->value,
            'nombre' => $this->negocio->name,
            'logo' => $this->negocio->logoPath === null ? null : route('negocios.profile.logo.show'),
            'activo' => $this->negocio->active,
        ];
    }
}
