<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Resources;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Src\Billing\Payments\Application\DTOs\PaymentDetails;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;

/**
 * @property-read PaymentDetails $resource
 */
final class PaymentResource extends JsonResource
{
    /** Wrap a payment. */
    public function __construct(private readonly PaymentDetails $payment)
    {
        parent::__construct($payment);
    }

    /**
     * Expose the payment; the amount is in colones with two decimals. A pending SINPE Móvil payment also
     * tells the buyer where to transfer and how much, and a pending PayPal one gives the address where the buyer approves it.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->payment->id,
            'venta_id' => $this->payment->saleId,
            'proveedor' => $this->payment->provider,
            'metodo_pago' => $this->payment->method,
            'monto' => (float) $this->payment->amount,
            'moneda' => $this->payment->currency,
            'estado' => $this->payment->status,
            'referencia' => $this->payment->reference,
            'codigo_rechazo' => $this->payment->declineCode,
            'creado_en' => $this->atom($this->payment->createdAt),
            'resuelto_en' => $this->payment->settledAt === null ? null : $this->atom($this->payment->settledAt),
            'instrucciones' => $this->instructions(),
            'url_aprobacion' => $this->payment->status === PaymentStatus::Pending->value ? $this->payment->approvalUrl : null,
            'referencia_reembolso' => $this->payment->refundReference,
        ];
    }

    /**
     * What the buyer has to do to finish a SINPE Móvil payment.
     *
     * @return array{numero_sinpe: string, monto: float, mensaje: string}|null
     */
    private function instructions(): ?array
    {
        if ($this->payment->method !== PaymentMethod::SinpeMovil->value || $this->payment->status !== PaymentStatus::Pending->value) {
            return null;
        }

        $number = (string) config('payments.sinpe_number');

        return [
            'numero_sinpe' => $number,
            'monto' => (float) $this->payment->amount,
            'mensaje' => __('payments.sinpe_instructions', ['number' => $number, 'amount' => $this->payment->amount]),
        ];
    }

    /** A moment in the time zone of the application, as the rest of the API shows it. */
    private function atom(DateTimeImmutable $moment): string
    {
        return Carbon::instance($moment)->setTimezone((string) config('app.timezone'))->toAtomString();
    }
}
