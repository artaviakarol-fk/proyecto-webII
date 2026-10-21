<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Contracts;

use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\UserId;

interface UserRepository
{
    /** Find the account registered with an email address. */
    public function findByEmail(Email $email): ?User;

    /** Find an account by its identifier. */
    public function find(UserId $id): ?User;
}
