<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ServiceUnavailableException;

/** The AI provider did not answer, or answered something that cannot be used; the sale can still be made by hand. */
final class SaleInterpreterUnavailableException extends ServiceUnavailableException
{
    /** The provider failed, timed out or is not configured. */
    public static function unavailable(): self
    {
        return new self('sales.ai_unavailable');
    }

    /** The provider answered something that does not pass the schema. */
    public static function invalidAnswer(): self
    {
        return new self('sales.ai_invalid_answer');
    }
}
