<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Payments\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Billing\Payments\Application\DTOs\PayableSale;
use Src\Billing\Payments\Application\DTOs\StartPaymentCommand;
use Src\Billing\Payments\Application\UseCases\GetPayment;
use Src\Billing\Payments\Application\UseCases\StartPayment;
use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\Exceptions\PaymentAlreadyStartedException;
use Src\Billing\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Billing\Payments\Domain\Exceptions\PaymentRejectedException;
use Src\Billing\Payments\Domain\Exceptions\SaleNotFoundException;
use Src\Billing\Payments\Domain\Exceptions\SaleNotPayableException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\Support\Billing\InMemoryPayableSales;
use Tests\Support\Billing\InMemoryPaymentRepository;
use Tests\Support\Shared\FixedNegocioContext;
use Tests\Support\Shared\ImmediateTransactionRunner;

final class PaymentUseCasesTest extends TestCase
{
    private const string NEGOCIO_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string OTHER_NEGOCIO_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d33';

    private const string OPEN_SALE = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d44';

    private const string PAID_SALE = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d45';

    private const string MISSING_SALE = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d46';

    private FakePaymentGateway $gateway;

    private InMemoryPaymentRepository $payments;

    private StartPayment $start;

    private GetPayment $get;

    /** An open sale of 3000 colones and a paid one, with a gateway that is told what to answer. */
    protected function setUp(): void
    {
        $sales = new InMemoryPayableSales;
        $sales->add(new PayableSale(new SaleId(self::OPEN_SALE), new Money(300000), true));
        $sales->add(new PayableSale(new SaleId(self::PAID_SALE), new Money(300000), false));

        $this->gateway = new FakePaymentGateway;
        $this->payments = new InMemoryPaymentRepository;
        $context = new FixedNegocioContext(new NegocioId(self::NEGOCIO_ID));
        $transaction = new ImmediateTransactionRunner;

        $this->start = new StartPayment($context, $transaction, $sales, $this->payments, $this->gateway);
        $this->get = new GetPayment($context, $transaction, $this->payments, $this->gateway);
    }

    /** The payment is pending until the provider says otherwise, and the charge carries the total of the sale. */
    public function test_a_payment_starts_pending_for_the_total_of_the_sale(): void
    {
        $payment = $this->start->execute($this->command(PaymentMethod::SinpeMovil), $this->now());

        $this->assertSame('pendiente', $payment->status);
        $this->assertSame('3000.00', $payment->amount);
        $this->assertSame('sinpe_movil', $payment->method);
        $this->assertSame('onvo', $payment->provider);
        $this->assertSame('intent_fake_1', $payment->reference);
        $this->assertCount(1, $this->gateway->charges);
        $this->assertSame(300000, $this->gateway->charges[0]->amount->cents);
        $this->assertSame('tok_123', $this->gateway->charges[0]->methodToken);
    }

    /** A card payment can be paid at once. */
    public function test_a_card_payment_can_be_paid_at_once(): void
    {
        $this->gateway->answerWith(new ChargeResult(PaymentStatus::Paid, new ChargeReference('intent_9'), null, ['status' => 'succeeded']));

        $payment = $this->start->execute($this->command(), $this->now());

        $this->assertSame('pagado', $payment->status);
        $this->assertNotNull($payment->settledAt);
    }

    /** A sale that is not in the negocio, or is not open, cannot be paid. */
    public function test_only_an_open_sale_of_the_negocio_can_be_paid(): void
    {
        try {
            $this->start->execute($this->command(saleId: self::MISSING_SALE), $this->now());
            $this->fail('A missing sale must not be paid.');
        } catch (SaleNotFoundException) {
            $this->assertSame(0, $this->payments->count());
        }

        $this->expectException(SaleNotPayableException::class);
        $this->start->execute($this->command(saleId: self::PAID_SALE), $this->now());
    }

    /** A sale cannot have two live payments. */
    public function test_a_second_payment_is_refused_while_the_first_one_lives(): void
    {
        $this->start->execute($this->command(), $this->now());

        $this->expectException(PaymentAlreadyStartedException::class);
        $this->start->execute($this->command(), $this->now());
    }

    /** After a failed payment the sale can be paid again. */
    public function test_a_new_payment_is_allowed_after_a_failed_one(): void
    {
        $this->gateway->answerWith(new ChargeResult(PaymentStatus::Failed, new ChargeReference('intent_1'), 'canceled'));
        $this->assertSame('fallido', $this->start->execute($this->command(), $this->now())->status);

        $this->gateway->answerWith(new ChargeResult(PaymentStatus::Pending, new ChargeReference('intent_2')));
        $this->assertSame('pendiente', $this->start->execute($this->command(), $this->now())->status);
    }

    /** An unknown way of paying is invalid data. */
    public function test_an_unknown_method_is_invalid(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->start->execute(new StartPaymentCommand(self::OPEN_SALE, 'cheque', 'tok_123'), $this->now());
    }

    /** A payment that the provider refuses reaches the caller with the reason. */
    public function test_a_refusal_of_the_provider_reaches_the_caller(): void
    {
        $this->gateway->failWith(PaymentRejectedException::create('issuer_declined'));

        try {
            $this->start->execute($this->command(), $this->now());
            $this->fail('A refused payment must raise.');
        } catch (PaymentRejectedException $rejection) {
            $this->assertSame(['code' => 'issuer_declined'], $rejection->parameters());
        }
    }

    /** A provider that does not answer reaches the caller as unavailable. */
    public function test_an_unavailable_provider_reaches_the_caller(): void
    {
        $this->gateway->failWith(GatewayUnavailableException::create());

        $this->expectException(GatewayUnavailableException::class);
        $this->start->execute($this->command(), $this->now());
    }

    /** The newest payment of a sale is shown; a sale without payment is reported as missing. */
    public function test_a_sale_without_payment_is_reported_as_missing(): void
    {
        $this->expectException(PaymentNotFoundException::class);

        $this->get->execute(self::OPEN_SALE, $this->now());
    }

    /** While the payment is pending, the provider is asked and the answer is kept. */
    public function test_a_pending_payment_is_refreshed_from_the_provider(): void
    {
        $this->start->execute($this->command(PaymentMethod::SinpeMovil), $this->now());
        $this->gateway->answerWith(new ChargeResult(PaymentStatus::Paid, new ChargeReference('intent_fake_1')));

        $payment = $this->get->execute(self::OPEN_SALE, $this->now());

        $this->assertSame('pagado', $payment->status);
        $this->assertCount(1, $this->gateway->statusChecks);

        $this->get->execute(self::OPEN_SALE, $this->now());
        $this->assertCount(1, $this->gateway->statusChecks, 'A paid payment is not asked about again.');
    }

    /** If the provider does not answer, the stored state is shown. */
    public function test_the_stored_state_is_shown_when_the_provider_is_down(): void
    {
        $this->start->execute($this->command(PaymentMethod::SinpeMovil), $this->now());
        $this->gateway->failWith(GatewayUnavailableException::create());

        $this->assertSame('pendiente', $this->get->execute(self::OPEN_SALE, $this->now())->status);
    }

    /** A payment of another negocio is never shown. */
    public function test_a_payment_of_another_negocio_is_not_shown(): void
    {
        $this->start->execute($this->command(), $this->now());
        $other = new GetPayment(new FixedNegocioContext(new NegocioId(self::OTHER_NEGOCIO_ID)), new ImmediateTransactionRunner, $this->payments, $this->gateway);

        $this->expectException(PaymentNotFoundException::class);
        $other->execute(self::OPEN_SALE, $this->now());
    }

    /** The request to pay a sale. */
    private function command(PaymentMethod $method = PaymentMethod::Card, string $saleId = self::OPEN_SALE): StartPaymentCommand
    {
        return new StartPaymentCommand($saleId, $method->value, 'tok_123');
    }

    /** A fixed moment. */
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-25 20:00:00');
    }
}
