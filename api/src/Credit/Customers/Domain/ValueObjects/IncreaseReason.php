<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

/** Why the cashier increased the limit: it is required, so the owner can review it later. */
final readonly class IncreaseReason
{
    public const int MAX_LENGTH = 255;

    public string $value;

    /** Trim the reason and reject empty or oversized values. */
    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException('credit.reason_invalid', ['max' => self::MAX_LENGTH]);
        }

        $this->value = $value;
    }
}
