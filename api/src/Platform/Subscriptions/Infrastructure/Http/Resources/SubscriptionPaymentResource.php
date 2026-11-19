<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure\Http\Resources;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Platform\Subscriptions\Domain\Entities\SubscriptionPayment;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentMethod;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentStatus;

/**
 * @property-read SubscriptionPayment $resource
 */
final class SubscriptionPaymentResource extends JsonResource
{
    /** Wrap a payment of the rent. */
    public function __construct(private readonly SubscriptionPayment $payment)
    {
        parent::__construct($payment);
    }

    /**
     * The payment, and while it waits: where to approve it (PayPal) or where to send the SINPE Móvil (ONVO).
     *
     * @return array{id: string, monto: float, meses: int, metodo: string, proveedor: string, estado: string, referencia: string|null, creado_el: string, url_aprobacion: string|null, instrucciones: array{numero_sinpe: string, monto: float, mensaje: string}|null}
     */
    public function toArray(Request $request): array
    {
        $pending = $this->payment->status === SubscriptionPaymentStatus::Pending;
        $approvalUrl = $this->payment->providerAnswer['approval_url'] ?? null;

        return [
            'id' => $this->payment->id,
            'monto' => (float) $this->payment->amount->toDecimal(),
            'meses' => $this->payment->months,
            'metodo' => $this->payment->method->value,
            'proveedor' => $this->payment->provider,
            'estado' => $this->payment->status->value,
            'referencia' => $this->payment->reference,
            'creado_el' => self::atom($this->payment->createdAt),
            'url_aprobacion' => $pending && is_string($approvalUrl) ? $approvalUrl : null,
            'instrucciones' => $pending && $this->payment->method === SubscriptionPaymentMethod::SinpeMovil ? $this->instructions() : null,
        ];
    }

    /** A moment in the time zone of the application, as the rest of the API shows it. */
    public static function atom(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone((string) config('app.timezone')))->format(DATE_ATOM);
    }

    /**
     * Where to send the SINPE Móvil and how much.
     *
     * @return array{numero_sinpe: string, monto: float, mensaje: string}
     */
    private function instructions(): array
    {
        $number = (string) config('payments.sinpe_number');
        $amount = $this->payment->amount->toDecimal();

        return [
            'numero_sinpe' => $number,
            'monto' => (float) $amount,
            'mensaje' => __('payments.sinpe_instructions', ['number' => $number, 'amount' => $amount]),
        ];
    }
}
