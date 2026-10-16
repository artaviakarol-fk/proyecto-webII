<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Domain\Entities;

use Src\Sales\Sales\Domain\Exceptions\InvalidSaleStateException;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Sales\Domain\ValueObjects\SaleLine;
use Src\Sales\Sales\Domain\ValueObjects\SaleStatus;
use Src\Sales\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\NegocioId;

final class Sale
{
    /**
     * Hold a sale paid in cash: who sold it, what was sold and the stage it is in.
     *
     * @param  list<SaleLine>  $lines
     */
    private function __construct(
        public readonly SaleId $id,
        public readonly NegocioId $negocioId,
        public readonly string $cashierId,
        public private(set) SaleStatus $status,
        public private(set) array $lines,
    ) {}

    /**
     * Open a sale; it needs at least one line and a product cannot appear twice.
     *
     * @param  list<SaleLine>  $lines
     */
    public static function open(SaleId $id, NegocioId $negocioId, string $cashierId, array $lines): self
    {
        self::guardLines($lines);

        return new self($id, $negocioId, $cashierId, SaleStatus::Open, $lines);
    }

    /**
     * Rebuild a sale from its stored state.
     *
     * @param  list<SaleLine>  $lines
     */
    public static function reconstitute(SaleId $id, NegocioId $negocioId, string $cashierId, SaleStatus $status, array $lines): self
    {
        return new self($id, $negocioId, $cashierId, $status, $lines);
    }

    /** The sum of the lines. */
    public function total(): Money
    {
        return array_reduce(
            $this->lines,
            fn (Money $total, SaleLine $line): Money => $total->plus($line->subtotal()),
            new Money(0),
        );
    }

    /**
     * Replace the lines while the sale is still open.
     *
     * @param  list<SaleLine>  $lines
     */
    public function revise(array $lines): void
    {
        $this->assertOpen();
        self::guardLines($lines);

        $this->lines = $lines;
    }

    /** Close the sale as paid in cash. */
    public function pay(): void
    {
        $this->assertOpen();

        $this->status = SaleStatus::Paid;
    }

    /** Cancel an open or paid sale; its units go back to the stock. */
    public function cancel(): void
    {
        if ($this->status === SaleStatus::Cancelled) {
            throw InvalidSaleStateException::alreadyCancelled();
        }

        $this->status = SaleStatus::Cancelled;
    }

    /** Only an open sale can change or be paid. */
    private function assertOpen(): void
    {
        if ($this->status !== SaleStatus::Open) {
            throw InvalidSaleStateException::notOpen();
        }
    }

    /**
     * A sale needs lines, and each product goes in one line only.
     *
     * @param  list<SaleLine>  $lines
     */
    private static function guardLines(array $lines): void
    {
        if ($lines === []) {
            throw new InvalidValueException('sales.sale_without_lines');
        }

        $products = array_map(fn (SaleLine $line): string => $line->productId->value, $lines);

        if (count($products) !== count(array_unique($products))) {
            throw new InvalidValueException('sales.duplicate_product');
        }
    }
}
