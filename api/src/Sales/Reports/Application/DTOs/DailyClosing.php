<?php

declare(strict_types=1);

namespace Src\Sales\Reports\Application\DTOs;

/** The income of one day: how many sales were paid, how much came in by each way of paying and the whole. */
final readonly class DailyClosing
{
    /** Ways of paying that always appear in the closing, even when they brought nothing that day. */
    public const array METHODS = ['efectivo', 'tarjeta', 'sinpe_movil', 'fiado'];

    /**
     * The totals are colones with two decimals, written as the database does.
     *
     * @param  array<string, string>  $totals  Keyed by the way of paying.
     */
    public function __construct(
        public string $date,
        public int $salesCount,
        public array $totals,
        public string $total,
    ) {}
}
