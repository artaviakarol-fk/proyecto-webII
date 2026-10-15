<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Application\UseCases;

use Src\Inventory\Receipts\Application\Contracts\ReceiptReader;
use Src\Inventory\Receipts\Application\DTOs\ReceiptDetails;
use Src\Inventory\Receipts\Domain\Exceptions\ReceiptNotFoundException;
use Src\Inventory\Receipts\Domain\ValueObjects\ReceiptId;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class GetGoodsReceipt
{
    /** Receive the negocio of the operation and the receipt reader. */
    public function __construct(
        private NegocioContext $context,
        private ReceiptReader $reader,
    ) {}

    /** Answer a receipt of the current negocio; one of another negocio is reported as missing. */
    public function execute(string $receiptId): ReceiptDetails
    {
        return $this->reader->find(new ReceiptId($receiptId), $this->context->current())
            ?? throw ReceiptNotFoundException::create();
    }
}
