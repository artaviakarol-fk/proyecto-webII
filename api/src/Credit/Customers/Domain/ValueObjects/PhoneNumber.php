<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class PhoneNumber
{
    public const int MAX_LENGTH = 20;

    /** Digits, optionally with a leading + and spaces or hyphens: "8888-8888", "+506 8888 8888". */
    public const string PATTERN = '/^\+?[0-9][0-9 -]{6,18}[0-9]$/';

    public string $value;

    /** Trim the number and reject anything that is not a phone number. */
    public function __construct(string $value)
    {
        $value = trim($value);

        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidValueException('credit.phone_invalid');
        }

        $this->value = $value;
    }
}
