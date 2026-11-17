<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Billing\Payments\Infrastructure\Gateways\PaymentGateways;

final readonly class GatewayOptionsController
{
    /**
     * List the gateways the buyer can choose, with the ways of paying each one offers.
     *
     * The public key of ONVO is public by design (the client uses it to create the payment method, so card data never
     * reaches this API); no secret is ever shown.
     */
    public function __invoke(PaymentGateways $gateways): JsonResponse
    {
        return new JsonResponse(['data' => $gateways->options()]);
    }
}
