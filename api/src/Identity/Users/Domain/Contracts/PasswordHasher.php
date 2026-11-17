<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Contracts;

interface PasswordHasher
{
    /** Tell whether a plain password matches a stored hash; without a hash, spend the same work and answer false. */
    public function check(string $plainPassword, ?string $hash): bool;

    /** Turn a plain password into the hash that is stored; the plain password is never kept. */
    public function hash(string $plainPassword): string;
}
