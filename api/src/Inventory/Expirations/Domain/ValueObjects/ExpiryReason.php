<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ExpiryReason
{
    public const int MAX_LENGTH = 200;

    public string $value;

    /** Accept a reason of one to 200 characters once the spaces around it are removed. */
    public function __construct(string $reason)
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > self::MAX_LENGTH) {
            throw new InvalidValueException('inventory.expiry_reason_invalid', ['max' => self::MAX_LENGTH]);
        }

        $this->value = $reason;
    }
}
