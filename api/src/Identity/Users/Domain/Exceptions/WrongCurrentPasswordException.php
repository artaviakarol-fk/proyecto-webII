<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class WrongCurrentPasswordException extends InvalidValueException
{
    /** The password that proves who is changing it does not match. */
    public static function create(): self
    {
        return new self('identity.wrong_current_password');
    }

    /** The new password is the same as the current one, so nothing would change. */
    public static function same(): self
    {
        return new self('identity.same_password');
    }
}
