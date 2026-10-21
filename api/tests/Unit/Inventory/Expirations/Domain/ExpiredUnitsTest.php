<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Expirations\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Inventory\Expirations\Domain\Entities\ExpiredUnits;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiredQuantity;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiredUnitsId;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiredUnitsStatus;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiryReason;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\NegocioId;

final class ExpiredUnitsTest extends TestCase
{
    private const string UUID = '0198a3b4-0000-7000-8000-000000000001';

    /** New expired units start on the shelf, with the trimmed reason. */
    public function test_marked_units_start_on_the_shelf(): void
    {
        $units = ExpiredUnits::mark(
            new ExpiredUnitsId(self::UUID),
            new NegocioId(self::UUID),
            new ProductId(self::UUID),
            new ExpiredQuantity(3),
            new ExpiryReason('  Vencida  '),
            self::UUID,
            new DateTimeImmutable('2026-10-15 10:00:00'),
        );

        $this->assertSame(ExpiredUnitsStatus::Active, $units->status);
        $this->assertSame(3, $units->quantity->value);
        $this->assertSame('Vencida', $units->reason->value);
    }

    /** The quantity is a whole number from one to the maximum. */
    public function test_quantity_limits(): void
    {
        $this->assertSame(1, new ExpiredQuantity(1)->value);
        $this->assertSame(ExpiredQuantity::MAX, new ExpiredQuantity(ExpiredQuantity::MAX)->value);

        foreach ([0, -1, ExpiredQuantity::MAX + 1] as $invalid) {
            try {
                new ExpiredQuantity($invalid);
                $this->fail("The quantity {$invalid} should be refused.");
            } catch (InvalidValueException $e) {
                $this->assertSame('inventory.expired_quantity_out_of_range', $e->translationKey());
            }
        }
    }

    /** The reason cannot be empty or longer than 200 characters. */
    public function test_reason_limits(): void
    {
        $this->assertSame(200, mb_strlen(new ExpiryReason(str_repeat('a', 200))->value));

        foreach (['', '   ', str_repeat('a', 201)] as $invalid) {
            try {
                new ExpiryReason($invalid);
                $this->fail('The reason should be refused.');
            } catch (InvalidValueException $e) {
                $this->assertSame('inventory.expiry_reason_invalid', $e->translationKey());
            }
        }
    }
}
