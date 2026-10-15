<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Application\DTOs;

final readonly class ReceiptDetails
{
    /**
     * A stored receipt as it is shown: amounts are decimal strings in colones.
     *
     * @param  list<array{producto_id: string, codigo: string, nombre: string, cantidad: int, costo_unitario: string, subtotal: string, costo_anterior: string|null, diferencia_costo: string|null, cambio_costo: bool, precio_venta: string}>  $lines
     */
    public function __construct(
        public string $id,
        public string $supplier,
        public string $date,
        public string $receivedById,
        public string $receivedByName,
        public string $registeredAt,
        public array $lines,
        public string $total,
    ) {}
}
