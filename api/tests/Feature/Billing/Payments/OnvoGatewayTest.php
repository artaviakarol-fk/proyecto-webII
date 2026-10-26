<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\Exceptions\PaymentRejectedException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Billing\Payments\Infrastructure\Gateways\OnvoGateway;
use Src\Shared\Domain\ValueObjects\Money;
use Tests\TestCase;

/** ONVO is simulated with the fake of the HTTP client: no test reaches the real service. */
final class OnvoGatewayTest extends TestCase
{
    private const string KEY = 'onvo_test_secret_key_unit';

    private const string BASE = 'https://api.onvopay.com/v1';

    /** The payment intent is created in colones for the exact amount and confirmed with the payment method of the client. */
    public function test_charge_creates_and_confirms_the_payment_intent(): void
    {
        Http::fake([
            'api.onvopay.com/v1/payment-intents' => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            'api.onvopay.com/v1/payment-intents/intent_1/confirm' => Http::response([
                'id' => 'intent_1',
                'status' => 'succeeded',
                'amount' => 300000,
                'currency' => 'CRC',
                'paymentMethod' => ['card' => ['last4' => '4242']],
            ], 201),
        ]);

        $result = $this->gateway()->charge($this->request());

        $this->assertSame(PaymentStatus::Paid, $result->status);
        $this->assertSame('intent_1', $result->reference->value);
        $this->assertNull($result->declineCode);
        $this->assertArrayNotHasKey('paymentMethod', $result->payload, 'Data of the payment method is not kept.');
        $this->assertSame(300000, $result->payload['amount']);

        Http::assertSent(fn (Request $request): bool => $request->url() === self::BASE.'/payment-intents'
            && $request->hasHeader('Authorization', 'Bearer '.self::KEY)
            && $request->data()['amount'] === 300000
            && $request->data()['currency'] === 'CRC'
            && $request->data()['metadata']['saleId'] === '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d44');
        Http::assertSent(fn (Request $request): bool => $request->url() === self::BASE.'/payment-intents/intent_1/confirm'
            && $request->data() === ['paymentMethodId' => 'pm_123']);
        Http::assertSentCount(2);
    }

    /** A SINPE Móvil payment stays pending until the transfer arrives. */
    public function test_a_processing_intent_is_pending(): void
    {
        $this->fakeIntent('processing');

        $result = $this->gateway()->charge($this->request(PaymentMethod::SinpeMovil));

        $this->assertSame(PaymentStatus::Pending, $result->status);
        $this->assertNull($result->declineCode);
    }

    /** An intent that went back to requires_payment_method or was canceled is a failed payment. */
    public function test_a_canceled_intent_is_failed(): void
    {
        $this->fakeIntent('canceled');

        $result = $this->gateway()->charge($this->request());

        $this->assertSame(PaymentStatus::Failed, $result->status);
        $this->assertSame('canceled', $result->declineCode);
    }

    /** A declined card is a success for ONVO: the reason is read from the last error of the intent. */
    public function test_a_declined_card_takes_its_reason_from_the_last_payment_error(): void
    {
        Http::fake([
            'api.onvopay.com/v1/payment-intents' => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            'api.onvopay.com/v1/payment-intents/intent_1/confirm' => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            'api.onvopay.com/v1/payment-intents/intent_1' => Http::response([
                'id' => 'intent_1',
                'status' => 'requires_payment_method',
                'lastPaymentError' => ['code' => 'declined', 'message' => 'Transaction declined due to test payment method used'],
            ]),
        ]);

        $result = $this->gateway()->charge($this->request());

        $this->assertSame(PaymentStatus::Failed, $result->status);
        $this->assertSame('declined', $result->declineCode);
        $this->assertArrayNotHasKey('lastPaymentError', $result->payload);
    }

    /** If the reason cannot be read the payment is failed anyway, with the state of the intent. */
    public function test_a_declined_card_is_failed_even_if_the_reason_cannot_be_read(): void
    {
        Http::fake([
            'api.onvopay.com/v1/payment-intents' => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            'api.onvopay.com/v1/payment-intents/intent_1/confirm' => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            'api.onvopay.com/v1/payment-intents/intent_1' => Http::response([], 500),
        ]);

        $result = $this->gateway()->charge($this->request());

        $this->assertSame(PaymentStatus::Failed, $result->status);
        $this->assertSame('requires_payment_method', $result->declineCode);
    }

    /** A card that the bank refuses is a rejection with the code of the bank. */
    public function test_a_declined_card_is_a_rejection(): void
    {
        Http::fake([
            'api.onvopay.com/v1/payment-intents' => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            'api.onvopay.com/v1/payment-intents/intent_1/confirm' => Http::response([
                'code' => 'cards.invalid_card_info',
                'statusCode' => 400,
                'message' => 'Card declined',
                'details' => ['card' => ['reason' => 'issuer_declined', 'declineCode' => 'insufficient_funds']],
            ], 400),
        ]);

        try {
            $this->gateway()->charge($this->request());
            $this->fail('A declined card must be a rejection.');
        } catch (PaymentRejectedException $rejection) {
            $this->assertSame(['code' => 'insufficient_funds'], $rejection->parameters());
        }
    }

    /** A server error, a wrong key or a body that is not what ONVO documents mean the provider cannot be used now. */
    public function test_other_failures_are_unavailability(): void
    {
        Http::fake(['api.onvopay.com/v1/payment-intents' => Http::sequence()
            ->push(['message' => 'boom'], 500)
            ->push(['statusCode' => 401], 401)
            ->push(['status' => 'requires_payment_method'], 201)]);

        $this->assertUnavailable(fn () => $this->gateway()->charge($this->request()));
        $this->assertUnavailable(fn () => $this->gateway()->charge($this->request()));
        $this->assertUnavailable(fn () => $this->gateway()->charge($this->request()));
    }

    /** If the connection fails the charge is not repeated: ONVO may have taken it. */
    public function test_a_failed_connection_is_unavailability_and_is_not_repeated(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls): never {
            $calls++;

            throw new ConnectionException('timeout');
        });

        $this->assertUnavailable(fn () => $this->gateway()->charge($this->request()));
        $this->assertSame(1, $calls);
    }

    /** The status of an intent is read with a GET and translated. */
    public function test_status_reads_the_payment_intent(): void
    {
        Http::fake(['api.onvopay.com/v1/payment-intents/intent_1' => Http::response(['id' => 'intent_1', 'status' => 'succeeded'])]);

        $result = $this->gateway()->status(new ChargeReference('intent_1'));

        $this->assertSame(PaymentStatus::Paid, $result->status);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request->hasHeader('Authorization', 'Bearer '.self::KEY));
    }

    /** The webhook is accepted only with the secret that ONVO sends in X-Webhook-Secret. */
    public function test_the_webhook_secret_is_checked(): void
    {
        $gateway = new OnvoGateway(self::KEY, self::BASE, 'whsec_test');

        $this->assertTrue($gateway->verifyWebhook('{}', ['x-webhook-secret' => 'whsec_test']));
        $this->assertFalse($gateway->verifyWebhook('{}', ['x-webhook-secret' => 'other']));
        $this->assertFalse($gateway->verifyWebhook('{}', []));
        $this->assertFalse(new OnvoGateway(self::KEY, self::BASE)->verifyWebhook('{}', ['x-webhook-secret' => '']), 'Without a configured secret nothing is accepted.');
    }

    /** Only test keys are accepted: no real money while the course lasts. */
    public function test_a_live_key_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OnvoGateway('onvo_live_secret_key_real', self::BASE);
    }

    /** The provider names itself, to be kept with each payment. */
    public function test_it_names_its_provider(): void
    {
        $this->assertSame('onvo', $this->gateway()->provider());
    }

    /** Answer the creation and the confirmation of an intent with a status. */
    private function fakeIntent(string $status): void
    {
        Http::fake([
            'api.onvopay.com/v1/payment-intents' => Http::response(['id' => 'intent_1', 'status' => 'requires_payment_method'], 201),
            'api.onvopay.com/v1/payment-intents/intent_1/confirm' => Http::response(['id' => 'intent_1', 'status' => $status], 201),
        ]);
    }

    /** The gateway with a test key. */
    private function gateway(): OnvoGateway
    {
        return new OnvoGateway(self::KEY, self::BASE, 'whsec_test');
    }

    /** A charge of 3000 colones. */
    private function request(PaymentMethod $method = PaymentMethod::Card): ChargeRequest
    {
        return new ChargeRequest(
            new PaymentId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d55'),
            new SaleId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d44'),
            new Money(300000),
            $method,
            'pm_123',
            'Venta 0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d44',
        );
    }

    /**
     * Assert that the call fails because the provider is unavailable.
     *
     * @param  callable(): mixed  $call
     */
    private function assertUnavailable(callable $call): void
    {
        try {
            $call();
            $this->fail('The provider should be reported as unavailable.');
        } catch (GatewayUnavailableException) {
            $this->addToAssertionCount(1);
        }
    }
}
