<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Sales\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Sales\Sales\Application\DTOs\SaleDetails;
use Src\Sales\Sales\Application\DTOs\SaleFilter;
use Src\Sales\Sales\Application\DTOs\SaleSummary;
use Src\Sales\Sales\Application\UseCases\GetSale;
use Src\Sales\Sales\Application\UseCases\ListSales;
use Src\Sales\Sales\Domain\Exceptions\SaleNotFoundException;
use Src\Sales\Sales\Domain\ValueObjects\SaleStatus;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Tests\Support\Sales\InMemorySaleReader;
use Tests\Support\Shared\FixedNegocioContext;

final class SaleQueriesTest extends TestCase
{
    private const string NEGOCIO_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string OTHER_NEGOCIO_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d33';

    private InMemorySaleReader $reader;

    private FixedNegocioContext $context;

    /** Three sales of the negocio and one of another negocio. */
    protected function setUp(): void
    {
        $this->reader = new InMemorySaleReader;
        $this->context = new FixedNegocioContext(new NegocioId(self::NEGOCIO_ID));

        $this->reader->add(self::NEGOCIO_ID, $this->sale(1, 'cobrada', '2026-10-15T09:00:00-06:00'));
        $this->reader->add(self::NEGOCIO_ID, $this->sale(2, 'anulada', '2026-10-15T10:00:00-06:00'));
        $this->reader->add(self::NEGOCIO_ID, $this->sale(3, 'abierta', '2026-10-16T08:00:00-06:00'));
        $this->reader->add(self::OTHER_NEGOCIO_ID, $this->sale(4, 'abierta', '2026-10-16T08:00:00-06:00'));
    }

    /** A sale of the negocio is found; one of another negocio is reported as missing. */
    public function test_gets_only_a_sale_of_the_negocio(): void
    {
        $this->assertSame('cobrada', new GetSale($this->context, $this->reader)->execute($this->id(1))->status);

        $this->expectException(SaleNotFoundException::class);
        new GetSale($this->context, $this->reader)->execute($this->id(4));
    }

    /** The listing is newest first, by pages, and filters by status and day. */
    public function test_lists_the_sales_of_the_negocio(): void
    {
        $list = new ListSales($this->context, $this->reader);

        $page = $list->execute(new SaleFilter, new PageRequest(1, 2));
        $this->assertSame(3, $page->total);
        $this->assertSame([$this->id(3), $this->id(2)], array_map(fn (SaleSummary $sale): string => $sale->id, $page->items));

        $this->assertSame(1, $list->execute(new SaleFilter(SaleStatus::Cancelled), new PageRequest)->total);
        $this->assertSame(2, $list->execute(new SaleFilter(day: new DateTimeImmutable('2026-10-15')), new PageRequest)->total);
    }

    /** A sale with one line of rice. */
    private function sale(int $number, string $status, string $createdAt): SaleDetails
    {
        $line = ['producto_id' => '0192f0c4-0000-7000-8000-000000000099', 'codigo' => 'ARROZ-1', 'nombre' => 'Arroz 1 kg', 'cantidad' => 1, 'precio_unitario' => '1500.00', 'subtotal' => '1500.00'];

        return new SaleDetails($this->id($number), $status, 'efectivo', '0192f0c4-0000-7000-8000-000000000077', 'Ana Caja', null, $createdAt, null, null, [$line], '1500.00');
    }

    /** A predictable sale identifier. */
    private function id(int $number): string
    {
        return sprintf('0192f0c4-0000-7000-8000-%012d', $number);
    }
}
