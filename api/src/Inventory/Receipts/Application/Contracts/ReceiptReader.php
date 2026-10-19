<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Application\Contracts;

use Src\Inventory\Receipts\Application\DTOs\ReceiptDetails;
use Src\Inventory\Receipts\Domain\ValueObjects\ReceiptId;
use Src\Shared\Domain\ValueObjects\NegocioId;

interface ReceiptReader
{
    /** Read a receipt of the negocio with the names of its products and of the person who registered it. */
    public function find(ReceiptId $id, NegocioId $negocioId): ?ReceiptDetails;
}
