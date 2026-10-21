<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Domain\Entities;

use Src\Sales\Sales\Domain\Exceptions\InvalidSaleStateException;
use Src\Sales\Sales\Domain\ValueObjects\BuyerIdNumber;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Sales\Domain\ValueObjects\SaleLine;
use Src\Sales\Sales\Domain\ValueObjects\SaleStatus;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\NegocioId;

final class Sale
{
    /**
     * Hold a sale paid in cash: who sold it, what was sold, who bought it when it is known and the stage it is in.
     *
     * @param  list<SaleLine>  $lines
     */
    private function __construct(
        public readonly SaleId $id,
        public readonly NegocioId $negocioId,
        public readonly string $cashierId,
        public private(set) SaleStatus $status,
        public private(set) array $lines,
        public private(set) ?BuyerIdNumber $buyer,
    ) {}

    /**
     * Open a sale; it needs at least one line, a product cannot appear twice and liquor needs the buyer's id number.
     *
     * @param  list<SaleLine>  $lines
     */
    public static function open(SaleId $id, NegocioId $negocioId, string $cashierId, array $lines, ?BuyerIdNumber $buyer = null): self
    {
        self::guardLines($lines, $buyer);

        return new self($id, $negocioId, $cashierId, SaleStatus::Open, $lines, $buyer);
    }

    /**
     * Rebuild a sale from its stored state.
     *
     * @param  list<SaleLine>  $lines
     */
    public static function reconstitute(SaleId $id, NegocioId $negocioId, string $cashierId, SaleStatus $status, array $lines, ?BuyerIdNumber $buyer = null): self
    {
        return new self($id, $negocioId, $cashierId, $status, $lines, $buyer);
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
     * Replace the lines while the sale is still open; without a new buyer, the one already registered stays.
     *
     * @param  list<SaleLine>  $lines
     */
    public function revise(array $lines, ?BuyerIdNumber $buyer = null): void
    {
        $this->assertOpen();
        $buyer ??= $this->buyer;
        self::guardLines($lines, $buyer);

        $this->lines = $lines;
        $this->buyer = $buyer;
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
     * A sale needs lines, each product goes in one line only, and a sale with liquor needs the buyer's id number.
     *
     * @param  list<SaleLine>  $lines
     */
    private static function guardLines(array $lines, ?BuyerIdNumber $buyer): void
    {
        if ($lines === []) {
            throw new InvalidValueException('sales.sale_without_lines');
        }

        $products = array_map(fn (SaleLine $line): string => $line->productId->value, $lines);

        if (count($products) !== count(array_unique($products))) {
            throw new InvalidValueException('sales.duplicate_product');
        }

        if ($buyer === null && array_any($lines, fn (SaleLine $line): bool => $line->liquor)) {
            throw new InvalidValueException('sales.buyer_id_required_for_liquor');
        }
    }
}
