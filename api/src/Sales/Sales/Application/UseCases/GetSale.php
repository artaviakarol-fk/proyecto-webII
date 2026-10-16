<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\UseCases;

use Src\Sales\Sales\Application\Contracts\SaleReader;
use Src\Sales\Sales\Application\DTOs\SaleDetails;
use Src\Sales\Sales\Domain\Exceptions\SaleNotFoundException;
use Src\Sales\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class GetSale
{
    /** Receive the negocio of the operation and the sale reader. */
    public function __construct(
        private NegocioContext $context,
        private SaleReader $reader,
    ) {}

    /** Answer a sale of the current negocio; one of another negocio is reported as missing. */
    public function execute(string $saleId): SaleDetails
    {
        return $this->reader->find(new SaleId($saleId), $this->context->current())
            ?? throw SaleNotFoundException::create();
    }
}
