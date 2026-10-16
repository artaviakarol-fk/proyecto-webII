<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Application\DTOs;

final readonly class SaleDetails
{
    /**
     * A stored sale as it is shown: amounts are decimal strings in colones.
     *
     * @param  list<array{producto_id: string, codigo: string, nombre: string, cantidad: int, precio_unitario: string, subtotal: string}>  $lines
     * @param  list<array{producto_id: string, nombre: string, unidades_vencidas: int}>  $expiredWarnings  Products of an open sale that have expired units on the shelf.
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $paymentMethod,
        public string $cashierId,
        public string $cashierName,
        public ?string $buyerIdNumber,
        public string $createdAt,
        public ?string $paidAt,
        public ?string $cancelledAt,
        public array $lines,
        public string $total,
        public array $expiredWarnings = [],
    ) {}
}
