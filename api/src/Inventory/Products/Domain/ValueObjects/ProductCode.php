<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ProductCode
{
    public const int MAX_LENGTH = 30;

    public const string PATTERN = '/^[A-Za-z0-9._-]+$/';

    public string $value;

    /** Accept a barcode or internal code: letters, digits, dot, dash and underscore. */
    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH || preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidValueException('inventory.code_invalid', ['max' => self::MAX_LENGTH]);
        }

        $this->value = $value;
    }
}
