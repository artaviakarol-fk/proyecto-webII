<?php

declare(strict_types=1);

namespace Src\Sales\Purchases\Infrastructure\Billing;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Billing\Payments\Application\DTOs\PaymentDetails;
use Src\Billing\Payments\Application\DTOs\StartPaymentCommand;
use Src\Billing\Payments\Application\UseCases\GetPayment;
use Src\Billing\Payments\Application\UseCases\StartPayment;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\Exceptions\SaleNotFoundException;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Billing\Payments\Infrastructure\Gateways\PaymentGateways;
use Src\Sales\Purchases\Application\Contracts\PurchasePayments;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class BillingPurchasePayments implements PurchasePayments
{
    /** Receive the factory of gateways, the payments, the negocio and the cliente of the request. */
    public function __construct(
        private PaymentGateways $gateways,
        private PaymentRepository $payments,
        private NegocioContext $context,
        private CurrentUser $user,
    ) {}

    /** Start the payment with the use case of the payments, built with the gateway that the cliente chose. */
    public function start(string $saleId, string $method, string $providerMethodId, ?string $gateway, DateTimeImmutable $now): PaymentDetails
    {
        $startPayment = app(StartPayment::class, ['gateway' => $this->gateways->make($gateway)]);

        return $startPayment->execute(new StartPaymentCommand($saleId, $method, $providerMethodId), $now);
    }

    /** Show the payment of a sale that the cliente made; it is followed with the gateway that charged it. */
    public function show(string $saleId, DateTimeImmutable $now): PaymentDetails
    {
        $isMine = DB::table('ventas')->where('id', $saleId)->where('cajero_id', $this->user->id())->exists();

        if (! $isMine) {
            throw SaleNotFoundException::create();
        }

        $provider = $this->payments->findLatestForSale(new SaleId($saleId), $this->context->current())?->provider;
        $getPayment = app(GetPayment::class, ['gateway' => $this->gateways->make($provider)]);

        return $getPayment->execute($saleId, $now);
    }
}
