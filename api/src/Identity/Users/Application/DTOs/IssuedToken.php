<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\DTOs;

final readonly class IssuedToken
{
    /**
     * Carry the plain text token, shown only once, its abilities and whether the account must change its temporary
     * password before it can use them.
     *
     * @param  list<string>  $abilities
     */
    public function __construct(
        public string $plainTextToken,
        public array $abilities,
        public bool $mustChangePassword = false,
    ) {}
}
