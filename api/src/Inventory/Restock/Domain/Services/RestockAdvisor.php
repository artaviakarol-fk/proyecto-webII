<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Domain\Services;

use InvalidArgumentException;
use Src\Inventory\Restock\Domain\ValueObjects\RestockAdvice;
use Src\Inventory\Restock\Domain\ValueObjects\RestockStatus;

/** Tells how urgent it is to restock a product and how many units to order, from its stock and what it sold. */
final readonly class RestockAdvisor
{
    /** A product that lasts this many days or less at its pace is critical. */
    public const int CRITICAL_DAYS = 3;

    /** A product that lasts this many days or less at its pace is low. */
    public const int LOW_DAYS = 7;

    /** The order should cover this many days of sales. */
    public const int TARGET_DAYS = 14;

    /**
     * Advise on a product that has `$stock` units and sold `$sold` in the last `$windowDays` days.
     *
     * The days it lasts are compared with whole numbers (stock × window against days × sold), so a product that
     * lasts exactly three days is critical and no rounding can tip it either way. A product with no stock is out of
     * stock, and one that did not sell is fine, because there is no pace to measure it by.
     */
    public function advise(int $stock, int $sold, int $windowDays): RestockAdvice
    {
        if ($stock < 0 || $sold < 0 || $windowDays < 1) {
            throw new InvalidArgumentException('Stock and sales cannot be negative and the window needs at least one day.');
        }

        $status = match (true) {
            $stock === 0 => RestockStatus::OutOfStock,
            $sold === 0 => RestockStatus::Ok,
            $stock * $windowDays <= self::CRITICAL_DAYS * $sold => RestockStatus::Critical,
            $stock * $windowDays <= self::LOW_DAYS * $sold => RestockStatus::Low,
            default => RestockStatus::Ok,
        };

        $needed = intdiv($sold * self::TARGET_DAYS + $windowDays - 1, $windowDays);

        return new RestockAdvice(
            round($sold / $windowDays, 2),
            $sold === 0 ? null : round($stock * $windowDays / $sold, 1),
            $status,
            max(0, $needed - $stock),
        );
    }
}
