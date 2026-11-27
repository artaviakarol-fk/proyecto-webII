<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Restock\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Inventory\Restock\Domain\Services\RestockAdvisor;
use Src\Inventory\Restock\Domain\ValueObjects\RestockStatus;

final class RestockAdvisorTest extends TestCase
{
    /** The status and the units to order for each mix of stock and sales in a 30-day window. */
    #[DataProvider('cases')]
    public function test_it_advises_on_stock_and_sales(int $stock, int $sold, RestockStatus $status, int $suggested): void
    {
        $advice = (new RestockAdvisor)->advise($stock, $sold, 30);

        $this->assertSame($status, $advice->status);
        $this->assertSame($suggested, $advice->suggested);
    }

    /** @return array<string, array{int, int, RestockStatus, int}> */
    public static function cases(): array
    {
        return [
            'no stock' => [0, 60, RestockStatus::OutOfStock, 28],
            'no stock and no sales' => [0, 0, RestockStatus::OutOfStock, 0],
            'exactly three days left' => [6, 60, RestockStatus::Critical, 22],
            'a bit more than three days' => [7, 60, RestockStatus::Low, 21],
            'exactly seven days left' => [14, 60, RestockStatus::Low, 14],
            'more than seven days' => [15, 60, RestockStatus::Ok, 13],
            'plenty of stock' => [100, 60, RestockStatus::Ok, 0],
            'stock but no sales' => [10, 0, RestockStatus::Ok, 0],
        ];
    }

    /** The average per day and the days left are rounded for the screen. */
    public function test_it_measures_the_pace(): void
    {
        $advice = (new RestockAdvisor)->advise(10, 50, 30);

        $this->assertSame(1.67, $advice->averagePerDay);
        $this->assertSame(6.0, $advice->daysLeft);
    }

    /** A product that did not sell has no days left to measure. */
    public function test_it_has_no_days_left_without_sales(): void
    {
        $this->assertNull((new RestockAdvisor)->advise(10, 0, 30)->daysLeft);
    }

    /** The order covers fourteen days, rounded up, in any window. */
    public function test_it_orders_for_fourteen_days_in_any_window(): void
    {
        $this->assertSame(10, (new RestockAdvisor)->advise(0, 5, 7)->suggested);
        $this->assertSame(1, (new RestockAdvisor)->advise(0, 1, 90)->suggested);
    }

    /** Negative figures or an empty window are a mistake of the caller. */
    #[DataProvider('invalid')]
    public function test_it_rejects_invalid_figures(int $stock, int $sold, int $window): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RestockAdvisor)->advise($stock, $sold, $window);
    }

    /** @return array<string, array{int, int, int}> */
    public static function invalid(): array
    {
        return ['negative stock' => [-1, 1, 30], 'negative sales' => [1, -1, 30], 'empty window' => [1, 1, 0]];
    }
}
