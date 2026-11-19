<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Platform\Subscriptions\Application\DTOs\SubscriptionView;
use Src\Platform\Subscriptions\Domain\Entities\SubscriptionPayment;
use Src\Platform\Subscriptions\Domain\ValueObjects\ServiceStatus;

/**
 * @property-read SubscriptionView $resource
 */
final class SubscriptionResource extends JsonResource
{
    /** Wrap the rent of the negocio. */
    public function __construct(private readonly SubscriptionView $subscription)
    {
        parent::__construct($subscription);
    }

    /**
     * Where the negocio is with the rent and the warning the administrator should see, if any.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'estado' => $this->subscription->status->value,
            'vence_el' => SubscriptionPaymentResource::atom($this->subscription->expiresAt),
            'dias_restantes' => $this->subscription->daysLeft,
            'en_prueba' => $this->subscription->trial,
            'precio_mensual' => (float) $this->subscription->monthlyPrice->toDecimal(),
            'aviso' => $this->warning(),
            'pagos' => array_map(fn (SubscriptionPayment $payment): array => new SubscriptionPaymentResource($payment)->toArray($request), $this->subscription->payments),
        ];
    }

    /** The warning to show: none while it is far from the date, yellow when it is close, red after it. */
    private function warning(): ?string
    {
        $days = $this->subscription->daysLeft;

        [$key, $replace] = match ($this->subscription->status) {
            ServiceStatus::Activo => [null, []],
            ServiceStatus::PorVencer => ['platform.warning_soon', ['days' => $days, 'what' => self::text($this->subscription->trial ? 'platform.trial' : 'platform.plan')]],
            ServiceStatus::EnGracia => ['platform.warning_grace', ['days' => max(1, $this->subscription->graceDays + $days)]],
            ServiceStatus::Suspendido => ['platform.warning_suspended', []],
        };

        return $key === null ? null : self::text($key, $replace);
    }

    /**
     * A message of the language files.
     *
     * @param  array<string, int|string>  $replace
     */
    private static function text(string $key, array $replace = []): string
    {
        $message = __($key, $replace);

        return is_string($message) ? $message : $key;
    }
}
