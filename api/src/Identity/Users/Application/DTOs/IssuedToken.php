<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\DTOs;

final readonly class IssuedToken
{
    /**
     * Carry the plain text token, shown only once, and its abilities.
     *
     * @param  list<string>  $abilities
     */
    public function __construct(
        public string $plainTextToken,
        public array $abilities,
    ) {}
}
