<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\Contracts;

use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\Exceptions\PaymentRejectedException;
use Src\Billing\Payments\Domain\Exceptions\RefundNotSupportedException;
use Src\Billing\Payments\Domain\Exceptions\RefundRejectedException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\RefundResult;
use Src\Shared\Domain\ValueObjects\Money;

interface PaymentGateway
{
    /** Name of the provider, as it is kept with every payment it handles. */
    public function provider(): string;

    /**
     * Ways of paying that this provider offers. The payments ask for it before charging, so a way that the provider
     * does not have is refused with a clear message instead of reaching the provider.
     *
     * @return non-empty-list<PaymentMethod>
     */
    public function supportedMethods(): array;

    /**
     * Create the charge with the provider and ask it to take the payment.
     *
     * @throws PaymentRejectedException when the provider or the bank refuses the payment
     * @throws GatewayUnavailableException when the provider does not answer or answers with an error
     */
    public function charge(ChargeRequest $request): ChargeResult;

    /**
     * Ask the provider what happened with a charge it already knows.
     *
     * @throws GatewayUnavailableException when the provider does not answer or answers with an error
     */
    public function status(ChargeReference $reference): ChargeResult;

    /**
     * Give back the amount of a charge that the provider already took.
     *
     * @throws RefundNotSupportedException when this provider does not give money back from this system
     * @throws RefundRejectedException when the provider refuses the refund
     * @throws GatewayUnavailableException when the provider does not answer or answers with an error
     */
    public function refund(ChargeReference $reference, Money $amount): RefundResult;

    /**
     * Tell whether a notification really comes from the provider.
     *
     * @param  array<string, string>  $headers  Headers of the request, with their names in lowercase.
     */
    public function verifyWebhook(string $payload, array $headers): bool;
}
