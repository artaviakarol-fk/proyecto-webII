<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Throwable;

/** A gateway that answers what the test tells it and remembers what it was asked. */
final class FakePaymentGateway implements PaymentGateway
{
    /** @var list<ChargeRequest> */
    public array $charges = [];

    /** @var list<ChargeReference> */
    public array $statusChecks = [];

    private ?Throwable $failure = null;

    private ChargeResult $answer;

    /** By default every charge stays pending with a fixed reference. */
    public function __construct()
    {
        $this->answer = new ChargeResult(PaymentStatus::Pending, new ChargeReference('intent_fake_1'));
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

    /** Name of the fake provider. */
    public function provider(): string
    {
        return 'onvo';
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
