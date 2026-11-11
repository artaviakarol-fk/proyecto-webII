<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class CreditIncreaseNotRevertibleException extends DomainException
{
    /** The increase was already reverted. */
    public static function alreadyReverted(): self
    {
        return new self('credit.increase_already_reverted');
    }

    /** The increase already stopped counting. */
    public static function expired(): self
    {
        return new self('credit.increase_expired');
    }
}
