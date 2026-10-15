<?php

declare(strict_types=1);

namespace Src\Inventory\Shared\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

/** An amount of colones kept in céntimos, so sums and comparisons never lose precision. */
final readonly class Money
{
    public const int MAX_CENTS = 9_999_999_999;

    /** Accept a non-negative amount in céntimos that fits a decimal(12,2) column. */
    public function __construct(public int $cents)
    {
        if ($cents < 0 || $cents > self::MAX_CENTS) {
            throw new InvalidValueException('inventory.amount_out_of_range');
        }
    }

    /** Read an amount written with up to two decimals, such as "1500", "1500.5" or "1500.50". */
    public static function fromDecimal(string $amount): self
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', trim($amount), $parts) !== 1) {
            throw new InvalidValueException('inventory.amount_out_of_range');
        }

        return new self((int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '0', 2, '0'));
    }

    /** Write the amount with exactly two decimals, as the database stores it. */
    public function toDecimal(): string
    {
        return intdiv($this->cents, 100).'.'.str_pad((string) ($this->cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Tell whether the amount is zero. */
    public function isZero(): bool
    {
        return $this->cents === 0;
    }
}
