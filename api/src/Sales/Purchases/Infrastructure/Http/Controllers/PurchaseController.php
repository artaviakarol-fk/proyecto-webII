<?php

declare(strict_types=1);

namespace Src\Sales\Purchases\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Billing\Payments\Infrastructure\Http\Resources\PaymentResource;
use Src\Sales\Purchases\Application\UseCases\BuyReservations;
use Src\Sales\Purchases\Application\UseCases\ShowPurchasePayment;
use Src\Sales\Purchases\Infrastructure\Http\Requests\PurchaseRequest;

final readonly class PurchaseController
{
    /**
     * Buy what the cliente reserved and pay it with the gateway it chose.
     *
     * The reservas become a sale and the gateway (ONVO or PayPal) is asked to charge it, all or nothing: if the stock is
     * not enough, liquor lacks the id number or the gateway refuses, the reservas stay active. It answers the payment;
     * while it is pending (SINPE Móvil, PayPal) its `venta_id` is used to ask how it stands.
     */
    public function store(PurchaseRequest $request, BuyReservations $buyReservations): JsonResponse
    {
        $payment = $buyReservations->execute($request->toCommand());

        return new PaymentResource($payment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', $request->url().'/'.$payment->saleId.'/pago');
    }

    /** Show how the payment of a purchase of the cliente stands; a purchase of somebody else answers 404. */
    public function show(string $venta, ShowPurchasePayment $showPurchasePayment): PaymentResource
    {
        return new PaymentResource($showPurchasePayment->execute(mb_strtolower($venta), now()->toDateTimeImmutable()));
    }
}
