<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\UseCases;

use Src\Identity\Users\Application\DTOs\IssuedToken;
use Src\Identity\Users\Application\DTOs\IssueTokenCommand;
use Src\Identity\Users\Domain\Contracts\NegocioServiceAccess;
use Src\Identity\Users\Domain\Contracts\PasswordHasher;
use Src\Identity\Users\Domain\Contracts\TokenIssuer;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Exceptions\InvalidCredentialsException;
use Src\Identity\Users\Domain\Exceptions\ServiceSuspendedException;
use Src\Identity\Users\Domain\ValueObjects\Email;

final readonly class IssueToken
{
    /** Receive the user repository, the password hashing and the token ports. */
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private TokenIssuer $tokens,
        private NegocioServiceAccess $service,
    ) {}

    /** Issue a token with the abilities of the role when the credentials belong to an active account. */
    public function execute(IssueTokenCommand $command): IssuedToken
    {
        $user = $this->users->findByEmail(new Email($command->email));
        $passwordMatches = $this->passwords->check($command->password, $user?->passwordHash);

        if ($user === null || ! $passwordMatches || ! $user->active) {
            throw InvalidCredentialsException::create();
        }

        // A negocio that did not pay its rent is not served: only its administrators enter, to see and to pay.
        if ($user->negocioId !== null && ! $user->administrates() && ! $this->service->serves($user->negocioId)) {
            throw ServiceSuspendedException::create();
        }

        $abilities = $user->abilities();

        return new IssuedToken($this->tokens->issue($user->id, $command->deviceName, $abilities), $abilities, $user->mustChangePassword);
    }
}
