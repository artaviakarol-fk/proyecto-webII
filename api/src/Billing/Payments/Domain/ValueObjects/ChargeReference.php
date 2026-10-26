<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

/** The identifier the provider gave to a charge. */
final readonly class ChargeReference
{
    public const int MAX_LENGTH = 255;

    /** Accept a reference with something in it that fits the column. */
    public function __construct(public string $value)
    {
        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException('payments.invalid_charge_reference');
        }
    }
}
