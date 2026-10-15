<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Products;

use PHPUnit\Framework\TestCase;
use Src\Inventory\Products\Domain\ValueObjects\Availability;
use Src\Inventory\Products\Domain\ValueObjects\Stock;

final class AvailabilityTest extends TestCase
{
    /** The sellable stock is the stock minus the expired units. */
    public function test_sellable_stock_excludes_expired_units(): void
    {
        $availability = new Availability(new Stock(10), 3);

        $this->assertSame(7, $availability->sellable);
        $this->assertFalse($availability->isSoldOut());
    }

    /** The sellable stock is never negative. */
    public function test_sellable_stock_is_never_negative(): void
    {
        $availability = new Availability(new Stock(2), 5);

        $this->assertSame(0, $availability->sellable);
        $this->assertTrue($availability->isSoldOut());
    }
}
