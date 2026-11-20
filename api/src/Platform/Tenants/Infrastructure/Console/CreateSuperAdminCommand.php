<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Infrastructure\Console;

use Illuminate\Console\Command;
use Src\Identity\Users\Infrastructure\Http\Requests\RegisterClienteRequest;
use Src\Platform\Tenants\Application\UseCases\CreateSuperAdmin;
use Src\Shared\Domain\Exceptions\DomainException;

final class CreateSuperAdminCommand extends Command
{
    /** @var string */
    protected $signature = 'plataforma:superadmin {correo : Correo con el que inicia sesión} {nombre : Nombre de la persona}';

    /** @var string */
    protected $description = 'Crea una cuenta de superadmin de la plataforma. La contraseña se escribe sin que se vea.';

    /** Ask the password twice without showing it and open the account. */
    public function handle(CreateSuperAdmin $create): int
    {
        $password = (string) $this->secret('Contraseña (al menos '.RegisterClienteRequest::PASSWORD_MIN_LENGTH.' caracteres)');
        if (mb_strlen($password) < RegisterClienteRequest::PASSWORD_MIN_LENGTH || mb_strlen($password) > RegisterClienteRequest::PASSWORD_MAX_LENGTH) {
            $this->error('La contraseña debe tener entre '.RegisterClienteRequest::PASSWORD_MIN_LENGTH.' y '.RegisterClienteRequest::PASSWORD_MAX_LENGTH.' caracteres.');

            return self::FAILURE;
        }

        if ($password !== (string) $this->secret('Repita la contraseña')) {
            $this->error('Las contraseñas no coinciden.');

            return self::FAILURE;
        }

        try {
            $user = $create->execute((string) $this->argument('nombre'), (string) $this->argument('correo'), $password);
        } catch (DomainException $exception) {
            $this->error(__($exception->translationKey(), $exception->parameters()));

            return self::FAILURE;
        }

        $this->info("Superadmin creado: {$user->email->value}");

        return self::SUCCESS;
    }
}
