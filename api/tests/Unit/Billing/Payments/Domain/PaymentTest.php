<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Payments\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\Exceptions\PaymentAlreadySettledException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

final class PaymentTest extends TestCase
{
    private const string NEGOCIO_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string SALE_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d44';

    private const string PAYMENT_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d55';

    /** A new payment is pending, has no reference and no final answer. */
    public function test_a_new_payment_is_pending(): void
    {
        $payment = $this->payment();

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->reference);
        $this->assertNull($payment->settledAt);
        $this->assertSame([], $payment->payload);
    }

    /** A payment needs an amount greater than zero. */
    public function test_a_payment_of_zero_is_not_valid(): void
    {
        $this->expectException(InvalidValueException::class);

        Payment::start(new PaymentId(self::PAYMENT_ID), new NegocioId(self::NEGOCIO_ID), new SaleId(self::SALE_ID), 'onvo', PaymentMethod::Card, new Money(0), $this->now());
    }

    /** A paid answer settles the payment and keeps the reference and the answer of the provider. */
    public function test_a_paid_answer_settles_the_payment(): void
    {
        $payment = $this->payment();
        $payment->apply(new ChargeResult(PaymentStatus::Paid, new ChargeReference('intent_1'), null, ['status' => 'succeeded']), $this->now('2026-10-25 21:00:00'));

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('intent_1', $payment->reference?->value);
        $this->assertSame(['status' => 'succeeded'], $payment->payload);
        $this->assertEquals($this->now('2026-10-25 21:00:00'), $payment->settledAt);
    }

    /** A pending answer keeps the payment open but remembers the reference. */
    public function test_a_pending_answer_keeps_the_payment_open(): void
    {
        $payment = $this->payment();
        $payment->apply(new ChargeResult(PaymentStatus::Pending, new ChargeReference('intent_1')), $this->now());

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('intent_1', $payment->reference?->value);
        $this->assertNull($payment->settledAt);
    }

    /** A failed answer settles the payment and keeps why it failed. */
    public function test_a_failed_answer_keeps_the_decline_code(): void
    {
        $payment = $this->payment();
        $payment->apply(new ChargeResult(PaymentStatus::Failed, new ChargeReference('intent_1'), 'canceled'), $this->now());

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('canceled', $payment->declineCode);
        $this->assertFalse($payment->status->isLive());
    }

    /** The same final answer again changes nothing. */
    public function test_the_same_final_answer_again_changes_nothing(): void
    {
        $payment = $this->payment();
        $result = new ChargeResult(PaymentStatus::Paid, new ChargeReference('intent_1'));
        $payment->apply($result, $this->now('2026-10-25 21:00:00'));
        $payment->apply($result, $this->now('2026-10-25 22:00:00'));

        $this->assertEquals($this->now('2026-10-25 21:00:00'), $payment->settledAt);
    }

    /** A payment that got a final answer cannot get a different one. */
    public function test_a_different_final_answer_is_an_error(): void
    {
        $payment = $this->payment();
        $payment->apply(new ChargeResult(PaymentStatus::Paid, new ChargeReference('intent_1')), $this->now());

        $this->expectException(PaymentAlreadySettledException::class);
        $payment->apply(new ChargeResult(PaymentStatus::Failed, new ChargeReference('intent_1'), 'canceled'), $this->now());
    }

    /** A reference needs something in it. */
    public function test_a_reference_cannot_be_empty(): void
    {
        $this->expectException(InvalidValueException::class);

        new ChargeReference('');
    }

    /** A payment of 3000 colones with a card. */
    private function payment(): Payment
    {
        return Payment::start(new PaymentId(self::PAYMENT_ID), new NegocioId(self::NEGOCIO_ID), new SaleId(self::SALE_ID), 'onvo', PaymentMethod::Card, new Money(300000), $this->now());
    }

    /** A fixed moment. */
    private function now(string $moment = '2026-10-25 20:00:00'): DateTimeImmutable
    {
        return new DateTimeImmutable($moment);
    }
}
