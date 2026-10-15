<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class Category
{
    public const int MAX_LENGTH = 60;

    public string $value;

    /** Trim the category, such as "Lácteos" or "Abarrotes", and reject empty or oversized values. */
    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException('inventory.category_invalid', ['max' => self::MAX_LENGTH]);
        }

        $this->value = $value;
    }
}
