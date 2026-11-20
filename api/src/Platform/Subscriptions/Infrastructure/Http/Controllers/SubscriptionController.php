<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Platform\Subscriptions\Application\UseCases\GetSubscription;
use Src\Platform\Subscriptions\Application\UseCases\PaySubscription;
use Src\Platform\Subscriptions\Application\UseCases\RefreshSubscriptionPayment;
use Src\Platform\Subscriptions\Infrastructure\Http\Requests\PaySubscriptionRequest;
use Src\Platform\Subscriptions\Infrastructure\Http\Resources\SubscriptionPaymentResource;
use Src\Platform\Subscriptions\Infrastructure\Http\Resources\SubscriptionResource;

final readonly class SubscriptionController
{
    /**
     * The rent of the negocio: its state, until when it is paid, the days left, the warning to show and the payments.
     *
     * The state is "activo", "por_vencer" (7 days or less), "en_gracia" (the date passed; everything still works for
     * 3 days) or "suspendido" (only the administration enters, to see and to pay).
     */
    public function show(GetSubscription $get): SubscriptionResource
    {
        return new SubscriptionResource($get->execute(CarbonImmutable::now()->toDateTimeImmutable()));
    }

    /**
     * Pay months of the rent with ONVO (card or SINPE Móvil) or PayPal.
     *
     * Cash and SINPE to the number of the platform are registered by a superadmin. A payment that waits for the
     * provider answers "pendiente", with where to approve it (PayPal) or where to send the SINPE.
     */
    public function pay(PaySubscriptionRequest $request, PaySubscription $pay): JsonResponse
    {
        [$payment] = $pay->execute($request->toCommand(), CarbonImmutable::now()->toDateTimeImmutable());

        return new SubscriptionPaymentResource($payment)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /** Follow a payment of the rent: the state is asked to the provider, and when it was paid the months are added. */
    public function refresh(string $pago, RefreshSubscriptionPayment $refresh): SubscriptionPaymentResource
    {
        return new SubscriptionPaymentResource($refresh->execute($pago, CarbonImmutable::now()->toDateTimeImmutable()));
    }
}
