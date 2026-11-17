<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Billing\Payments\Application\UseCases\GetPayment;
use Src\Billing\Payments\Application\UseCases\RefundPayment;
use Src\Billing\Payments\Application\UseCases\StartPayment;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Billing\Payments\Infrastructure\Gateways\PaymentGateways;
use Src\Billing\Payments\Infrastructure\Http\Requests\StartPaymentRequest;
use Src\Billing\Payments\Infrastructure\Http\Resources\PaymentResource;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class SalePaymentController
{
    /** Receive the factory of gateways, the payments and the negocio of the request. */
    public function __construct(
        private PaymentGateways $gateways,
        private PaymentRepository $payments,
        private NegocioContext $context,
    ) {}

    /** Start the payment of an open sale with the provider chosen for this payment (or the configured one). */
    public function store(string $venta, StartPaymentRequest $request): JsonResponse
    {
        $startPayment = app(StartPayment::class, ['gateway' => $this->gateways->make($request->gateway())]);
        $payment = $startPayment->execute($request->toCommand($venta), now()->toDateTimeImmutable());

        return new PaymentResource($payment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', $request->url());
    }

    /** Show the newest payment of a sale; while it is pending, the provider that charged it is asked what happened. */
    public function show(string $venta): PaymentResource
    {
        $getPayment = app(GetPayment::class, ['gateway' => $this->gateways->make($this->providerOf($venta))]);

        return new PaymentResource($getPayment->execute($venta, now()->toDateTimeImmutable()));
    }

    /** Give back the whole amount of the paid payment of a sale, with the provider that charged it. */
    public function refund(string $venta): PaymentResource
    {
        $refundPayment = app(RefundPayment::class, ['gateway' => $this->gateways->make($this->providerOf($venta))]);

        return new PaymentResource($refundPayment->execute($venta));
    }

    /** The provider that charged the newest payment of the sale; null when it has none, so the configured one is used. */
    private function providerOf(string $saleId): ?string
    {
        return $this->payments->findLatestForSale(new SaleId($saleId), $this->context->current())?->provider;
    }
}
