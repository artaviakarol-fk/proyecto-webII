<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Billing\Payments\Application\UseCases\GetPayment;
use Src\Billing\Payments\Application\UseCases\StartPayment;
use Src\Billing\Payments\Infrastructure\Http\Requests\StartPaymentRequest;
use Src\Billing\Payments\Infrastructure\Http\Resources\PaymentResource;

final readonly class SalePaymentController
{
    /** Start the payment of an open sale with the provider chosen in the configuration. */
    public function store(string $venta, StartPaymentRequest $request, StartPayment $startPayment): JsonResponse
    {
        $payment = $startPayment->execute($request->toCommand($venta), now()->toDateTimeImmutable());

        return new PaymentResource($payment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', $request->url());
    }

    /** Show the newest payment of a sale; while it is pending, the provider is asked what happened. */
    public function show(string $venta, GetPayment $getPayment): PaymentResource
    {
        return new PaymentResource($getPayment->execute($venta, now()->toDateTimeImmutable()));
    }
}
