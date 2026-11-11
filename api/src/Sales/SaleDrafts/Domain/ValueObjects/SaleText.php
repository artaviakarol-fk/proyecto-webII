<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

/** What the cashier wrote, such as "anote 2 leches y 1 pan a doña María en fiado". */
final readonly class SaleText
{
    public const int MAX_LENGTH = 500;

    public string $value;

    /** Trim the text and reject empty or oversized values. */
    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException('sales.ai_text_invalid', ['max' => self::MAX_LENGTH]);
        }

        $this->value = $value;
    }
}
