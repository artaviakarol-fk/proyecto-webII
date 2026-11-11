<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;

/** The base amount a customer may owe on fiado, set by the administration. */
final readonly class CreditLimit
{
    /** Accept a limit between zero and the maximum the negocio allows (₡90 000 by default). */
    public static function upTo(Money $amount, Money $maximum): self
    {
        if ($amount->cents > $maximum->cents) {
            throw new InvalidValueException('credit.limit_above_maximum', ['max' => Colones::format($maximum)]);
        }

        return new self($amount);
    }

    /** Rebuild a stored limit, already validated when it was set. */
    public static function reconstitute(Money $amount): self
    {
        return new self($amount);
    }

    private function __construct(public Money $amount) {}
}
