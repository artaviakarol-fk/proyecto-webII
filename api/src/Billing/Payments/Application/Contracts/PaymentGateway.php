<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\Contracts;

use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\Exceptions\PaymentRejectedException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;

interface PaymentGateway
{
    /** Name of the provider, as it is kept with every payment it handles. */
    public function provider(): string;

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
     * Tell whether a notification really comes from the provider.
     *
     * @param  array<string, string>  $headers  Headers of the request, with their names in lowercase.
     */
    public function verifyWebhook(string $payload, array $headers): bool;
}
