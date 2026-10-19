<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class BuyerIdNumber
{
    public const int MIN_DIGITS = 9;

    public const int MAX_DIGITS = 12;

    public string $value;

    /**
     * Keep the digits of an id number written with hyphens or spaces, such as "1-1111-1111".
     * The cashier checks it by hand: it is never verified against an outside service.
     */
    public function __construct(string $idNumber)
    {
        $digits = preg_replace('/[\s-]/', '', trim($idNumber)) ?? '';

        if (preg_match('/^\d{'.self::MIN_DIGITS.','.self::MAX_DIGITS.'}$/', $digits) !== 1) {
            throw new InvalidValueException('sales.buyer_id_invalid', ['min' => self::MIN_DIGITS, 'max' => self::MAX_DIGITS]);
        }

        $this->value = $digits;
    }
}
