<?php

declare(strict_types=1);

namespace Src\Sales\Reports\Application\UseCases;

use DateTimeImmutable;
use Src\Sales\Reports\Application\Contracts\DailyClosingReader;
use Src\Sales\Reports\Application\DTOs\DailyClosing;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\ValueObjects\Money;

final readonly class GetDailyClosing
{
    /** Receive the negocio of the operation and the reader of the sales. */
    public function __construct(
        private NegocioContext $context,
        private DailyClosingReader $reader,
    ) {}

    /**
     * The income of a day of the current negocio: the number of paid sales and the total by way of paying. It only adds
     * what was sold; it asks for no cash count and says nothing about missing or extra money.
     */
    public function execute(DateTimeImmutable $day): DailyClosing
    {
        $collected = $this->reader->collectedOn($this->context->current(), $day);

        $totals = [];
        $count = 0;
        $whole = new Money(0);

        foreach ([...DailyClosing::METHODS, ...array_diff(array_keys($collected), DailyClosing::METHODS)] as $method) {
            $amount = $collected[$method]['total'] ?? new Money(0);
            $totals[$method] = $amount->toDecimal();
            $count += $collected[$method]['count'] ?? 0;
            $whole = $whole->plus($amount);
        }

        return new DailyClosing($day->format('Y-m-d'), $count, $totals, $whole->toDecimal());
    }
}
