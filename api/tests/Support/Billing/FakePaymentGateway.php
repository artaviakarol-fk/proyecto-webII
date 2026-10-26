<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Billing\Payments\Domain\ValueObjects\RefundResult;
use Src\Billing\Payments\Domain\ValueObjects\RefundStatus;
use Src\Shared\Domain\ValueObjects\Money;
use Throwable;

/** A gateway that answers what the test tells it and remembers what it was asked. */
final class FakePaymentGateway implements PaymentGateway
{
    /** @var list<ChargeRequest> */
    public array $charges = [];

    /** @var list<ChargeReference> */
    public array $statusChecks = [];

    /** @var list<array{reference: ChargeReference, amount: Money}> */
    public array $refunds = [];

    /** @var non-empty-list<PaymentMethod> */
    private array $methods = [PaymentMethod::Card, PaymentMethod::SinpeMovil];

    private string $name = 'onvo';

    private ?Throwable $refundFailure = null;

    private RefundResult $refundAnswer;

    private ?Throwable $failure = null;

    private ChargeResult $answer;

    /** By default every charge stays pending with a fixed reference. */
    public function __construct()
    {
        $this->answer = new ChargeResult(PaymentStatus::Pending, new ChargeReference('intent_fake_1'));
        $this->refundAnswer = new RefundResult(RefundStatus::Succeeded, 'refund_fake_1');
    }

    /**
     * Behave as another provider, with its name and its ways of paying.
     *
     * @param  non-empty-list<PaymentMethod>  $methods
     */
    public function actAs(string $name, array $methods): self
    {
        $this->name = $name;
        $this->methods = $methods;

        return $this;
    }

    /** Answer every refund with this result. */
    public function refundWith(RefundResult $answer): self
    {
        $this->refundAnswer = $answer;
        $this->refundFailure = null;

        return $this;
    }

    /** Fail every refund with this exception. */
    public function failRefundWith(Throwable $failure): self
    {
        $this->refundFailure = $failure;

        return $this;
    }

    /** Answer every call with this result. */
    public function answerWith(ChargeResult $answer): self
    {
        $this->answer = $answer;
        $this->failure = null;

        return $this;
    }

    /** Fail every call with this exception. */
    public function failWith(Throwable $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    /** Name of the fake provider; ONVO unless the test says otherwise. */
    public function provider(): string
    {
        return $this->name;
    }

    /** The ways of paying the test chose; by default card and SINPE Móvil, like ONVO. */
    public function supportedMethods(): array
    {
        return $this->methods;
    }

    /** Remember the charge and answer or fail as told. */
    public function charge(ChargeRequest $request): ChargeResult
    {
        $this->charges[] = $request;

        return $this->respond();
    }

    /** Remember the question and answer or fail as told. */
    public function status(ChargeReference $reference): ChargeResult
    {
        $this->statusChecks[] = $reference;

        return $this->respond();
    }

    /** Remember the refund and answer or fail as told. */
    public function refund(ChargeReference $reference, Money $amount): RefundResult
    {
        $this->refunds[] = ['reference' => $reference, 'amount' => $amount];

        if ($this->refundFailure !== null) {
            throw $this->refundFailure;
        }

        return $this->refundAnswer;
    }

    /** Accept only the notification that carries the secret "ok". */
    public function verifyWebhook(string $payload, array $headers): bool
    {
        return ($headers['x-webhook-secret'] ?? '') === 'ok';
    }

    /** The result the test chose, or the failure. */
    private function respond(): ChargeResult
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->answer;
    }
}
