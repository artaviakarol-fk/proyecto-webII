<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;

// La agenda de reservas de un negocio: solo su personal la escucha. El negocio se compara con el de la cuenta
// del token, nunca con un dato que mande el navegador; un cliente o el personal de otro negocio recibe 403.
Broadcast::channel('negocio.{negocioId}.agenda', function (UserModel $usuario, string $negocioId): bool {
    $esPersonal = in_array(Role::tryFrom($usuario->rol), Role::staff(), true);

    return $esPersonal && $usuario->negocio_id !== null && $usuario->negocio_id === mb_strtolower($negocioId);
});
