<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Users\Domain\Entities\User;

/**
 * @property-read User $resource
 */
final class CurrentUserResource extends JsonResource
{
    /** Wrap the account of the request. */
    public function __construct(private readonly User $user)
    {
        parent::__construct($user);
    }

    /**
     * Expose who is logged in, their role, what the role allows and their negocio; never the password.
     *
     * @return array{id: string, nombre: string, correo: string, rol: string, abilities: list<string>, negocio_id: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user->id->value,
            'nombre' => $this->user->name,
            'correo' => $this->user->email->value,
            'rol' => $this->user->role->value,
            'abilities' => $this->user->role->abilities(),
            'negocio_id' => $this->user->negocioId->value,
        ];
    }
}
