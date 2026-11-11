<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Credit\Customers\Application\DTOs\CreditPaymentReceipt;

/**
 * @property-read CreditPaymentReceipt $resource
 */
final class CreditPaymentResource extends JsonResource
{
    /** Wrap the receipt of an abono. */
    public function __construct(private readonly CreditPaymentReceipt $receipt)
    {
        parent::__construct($receipt);
    }

    /**
     * The abono with what the customer owed before and what remains, and the link to its printable receipt.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->receipt->id,
            'cliente' => ['id' => $this->receipt->customerId, 'nombre' => $this->receipt->customerName],
            'monto' => (float) $this->receipt->amount,
            'metodo_pago' => $this->receipt->method,
            'saldo_anterior' => (float) $this->receipt->balanceBefore,
            'saldo_restante' => (float) $this->receipt->balanceAfter,
            'recibido_por' => ['id' => $this->receipt->receivedById, 'nombre' => $this->receipt->receivedByName],
            'fecha' => $this->receipt->receivedAt,
            'recibo' => route('credit.payments.receipt', $this->receipt->id),
        ];
    }
}
