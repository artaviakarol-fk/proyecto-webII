<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Sales\Domain;

use PHPUnit\Framework\TestCase;
use Src\Sales\Sales\Domain\Entities\Sale;
use Src\Sales\Sales\Domain\Exceptions\InvalidSaleStateException;
use Src\Sales\Sales\Domain\ValueObjects\BuyerIdNumber;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Sales\Domain\ValueObjects\SaleLine;
use Src\Sales\Sales\Domain\ValueObjects\SaleStatus;
use Src\Sales\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

final class SaleTest extends TestCase
{
    private const string ARROZ = '018f0000-0000-7000-8000-000000000001';

    private const string FRIJOLES = '018f0000-0000-7000-8000-000000000002';

    /** The total is the sum of the subtotals of the lines. */
    public function test_total_adds_up_the_lines(): void
    {
        $sale = $this->openSale([$this->line(self::ARROZ, 2, '1500.00'), $this->line(self::FRIJOLES, 3, '1999.50')]);

        $this->assertSame('8998.50', $sale->total()->toDecimal());
        $this->assertSame(SaleStatus::Open, $sale->status);
    }

    /** A sale needs at least one line. */
    public function test_sale_without_lines_is_rejected(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->openSale([]);
    }

    /** A product cannot appear in two lines of the same sale. */
    public function test_duplicate_product_is_rejected(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->openSale([$this->line(self::ARROZ, 1, '1500.00'), $this->line(self::ARROZ, 2, '1500.00')]);
    }

    /** A line sells between one and the maximum units. */
    public function test_quantity_must_be_in_range(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->line(self::ARROZ, SaleLine::MAX_QUANTITY + 1, '1500.00');
    }

    /** An open sale changes its lines. */
    public function test_open_sale_can_be_revised(): void
    {
        $sale = $this->openSale([$this->line(self::ARROZ, 2, '1500.00')]);

        $sale->revise([$this->line(self::FRIJOLES, 1, '2000.00')]);

        $this->assertSame('2000.00', $sale->total()->toDecimal());
    }

    /** A paid sale no longer changes or gets paid again. */
    public function test_paid_sale_cannot_be_revised_or_paid_again(): void
    {
        $sale = $this->openSale([$this->line(self::ARROZ, 2, '1500.00')]);
        $sale->pay();

        $this->assertSame(SaleStatus::Paid, $sale->status);

        try {
            $sale->revise([$this->line(self::ARROZ, 1, '1500.00')]);
            $this->fail('A paid sale was revised.');
        } catch (InvalidSaleStateException) {
            $this->assertSame('3000.00', $sale->total()->toDecimal());
        }

        $this->expectException(InvalidSaleStateException::class);
        $sale->pay();
    }

    /** An open or paid sale can be cancelled, and a cancelled one cannot be cancelled again. */
    public function test_sale_is_cancelled_once(): void
    {
        $sale = $this->openSale([$this->line(self::ARROZ, 2, '1500.00')]);
        $sale->pay();
        $sale->cancel();

        $this->assertSame(SaleStatus::Cancelled, $sale->status);
        $this->assertFalse($sale->status->holdsStock());

        $this->expectException(InvalidSaleStateException::class);
        $sale->cancel();
    }

    /** A sale with liquor cannot be opened without the id number of the buyer. */
    public function test_sale_with_liquor_needs_the_buyer_id(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->openSale([$this->line(self::ARROZ, 1, '1500.00'), $this->line(self::FRIJOLES, 1, '2000.00', liquor: true)]);
    }

    /** With the id number of the buyer, a sale with liquor opens and keeps it. */
    public function test_sale_with_liquor_keeps_the_buyer_id(): void
    {
        $sale = $this->openSale([$this->line(self::FRIJOLES, 1, '2000.00', liquor: true)], new BuyerIdNumber('1-1111-1111'));

        $this->assertSame('111111111', $sale->buyer?->value);
    }

    /** A sale without liquor does not need the buyer. */
    public function test_sale_without_liquor_does_not_need_the_buyer_id(): void
    {
        $sale = $this->openSale([$this->line(self::ARROZ, 1, '1500.00')]);

        $this->assertNull($sale->buyer);
    }

    /** Revising keeps the buyer that was registered, and adding liquor without any buyer is rejected. */
    public function test_revising_keeps_the_buyer_and_checks_the_liquor(): void
    {
        $sale = $this->openSale([$this->line(self::FRIJOLES, 1, '2000.00', liquor: true)], new BuyerIdNumber('1-1111-1111'));

        $sale->revise([$this->line(self::FRIJOLES, 2, '2000.00', liquor: true)]);
        $this->assertSame('111111111', $sale->buyer?->value);

        $withoutBuyer = $this->openSale([$this->line(self::ARROZ, 1, '1500.00')]);
        $this->expectException(InvalidValueException::class);
        $withoutBuyer->revise([$this->line(self::FRIJOLES, 1, '2000.00', liquor: true)]);
    }

    /**
     * Open a sale of a negocio with the given lines.
     *
     * @param  list<SaleLine>  $lines
     */
    private function openSale(array $lines, ?BuyerIdNumber $buyer = null): Sale
    {
        return Sale::open(
            new SaleId('018f0000-0000-7000-8000-0000000000aa'),
            new NegocioId('018f0000-0000-7000-8000-0000000000bb'),
            '018f0000-0000-7000-8000-0000000000cc',
            $lines,
            $buyer,
        );
    }

    /** Build a line of a product with a decimal price. */
    private function line(string $productId, int $quantity, string $price, bool $liquor = false): SaleLine
    {
        return new SaleLine(new ProductId($productId), $quantity, Money::fromDecimal($price), $liquor);
    }
}
