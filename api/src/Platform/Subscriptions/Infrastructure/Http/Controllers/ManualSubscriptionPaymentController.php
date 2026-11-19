<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Platform\Subscriptions\Application\UseCases\RegisterManualSubscriptionPayment;
use Src\Platform\Subscriptions\Infrastructure\Http\Requests\ManualPaymentRequest;
use Src\Platform\Subscriptions\Infrastructure\Http\Resources\SubscriptionPaymentResource;

final readonly class ManualSubscriptionPaymentController
{
    /**
     * Register a payment of the rent received in cash or by SINPE to the number of the platform.
     *
     * Only a superadmin. It is approved at once and its months are added to the date of the negocio.
     */
    public function __invoke(ManualPaymentRequest $request, string $negocio, RegisterManualSubscriptionPayment $register): JsonResponse
    {
        return new SubscriptionPaymentResource($register->execute($request->toCommand($negocio), CarbonImmutable::now()->toDateTimeImmutable()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
