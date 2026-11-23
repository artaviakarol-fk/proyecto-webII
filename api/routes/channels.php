<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;

// La agenda de un negocio (sus reservas en vivo): solo el personal de caja de ESE negocio la escucha.
// Es el aislamiento por negocio de la API, ahora también en el WebSocket. El negocio se compara con el de la
// cuenta del token, nunca con algo que mande el navegador.
Broadcast::channel('negocio.{negocioId}.agenda', function (UserModel $usuario, string $negocioId): bool {
    return $usuario->activo
        && $usuario->negocio_id === $negocioId
        && in_array($usuario->rol, [Role::Administrador->value, Role::Cajero->value, Role::Supervisor->value], true);
});
