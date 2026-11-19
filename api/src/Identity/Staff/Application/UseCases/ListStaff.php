<?php

declare(strict_types=1);

namespace Src\Identity\Staff\Application\UseCases;

use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class ListStaff
{
    /** Receive the negocio of the request and the accounts. */
    public function __construct(
        private NegocioContext $context,
        private UserRepository $users,
    ) {}

    /**
     * The staff of the negocio, active or not, ordered by name; the clientes are not staff.
     *
     * @return list<User>
     */
    public function execute(): array
    {
        return $this->users->staffOf($this->context->current());
    }
}
