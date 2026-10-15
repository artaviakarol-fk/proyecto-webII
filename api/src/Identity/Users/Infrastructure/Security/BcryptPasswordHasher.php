<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Security;

use Illuminate\Support\Facades\Hash;
use Src\Identity\Users\Domain\Contracts\PasswordHasher;

final readonly class BcryptPasswordHasher implements PasswordHasher
{
    private const string DRIVER = 'bcrypt';

    /** Compare the password with a bcrypt hash; without one, hash it anyway so the time is the same. */
    public function check(string $plainPassword, ?string $hash): bool
    {
        if ($hash === null) {
            Hash::driver(self::DRIVER)->make($plainPassword);

            return false;
        }

        return Hash::driver(self::DRIVER)->check($plainPassword, $hash);
    }
}
