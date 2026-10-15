<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Domain\Contracts;

use Src\Inventory\Receipts\Domain\Entities\GoodsReceipt;
use Src\Inventory\Receipts\Domain\Exceptions\StockLimitExceededException;
use Src\Inventory\Receipts\Domain\ValueObjects\ReceiptId;

interface GoodsReceiptRepository
{
    /** Generate the identity for a new receipt. */
    public function nextId(): ReceiptId;

    /**
     * Store the receipt and add each line to the stock of its product atomically, locking the product
     * rows, updating their current cost and leaving one inventory movement per line.
     *
     * @throws StockLimitExceededException
     */
    public function save(GoodsReceipt $receipt): void;
}
