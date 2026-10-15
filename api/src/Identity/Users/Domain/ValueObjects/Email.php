<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class Email
{
    public const int MAX_LENGTH = 255;

    public string $value;

    /** Normalise the address to lowercase without surrounding spaces and reject a malformed one. */
    public function __construct(string $value)
    {
        $normalised = mb_strtolower(trim($value));

        if (mb_strlen($normalised) > self::MAX_LENGTH || filter_var($normalised, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidValueException('identity.invalid_email');
        }

        $this->value = $normalised;
    }
}
