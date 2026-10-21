<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\UseCases;

use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\Exceptions\InvalidCredentialsException;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Shared\Domain\Contracts\CurrentUser;

final readonly class GetCurrentUser
{
    /** Receive the account of the request and the user repository. */
    public function __construct(
        private CurrentUser $user,
        private UserRepository $users,
    ) {}

    /** Answer the account behind the token of the request; an account deactivated after the login is refused. */
    public function execute(): User
    {
        $user = $this->users->find(new UserId($this->user->id()));

        return $user !== null && $user->active ? $user : throw InvalidCredentialsException::create();
    }
}
