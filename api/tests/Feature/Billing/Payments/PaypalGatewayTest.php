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
use Src\Billing\Payments\Infrastructure\Gateways\PaypalGateway;
use Src\Shared\Domain\ValueObjects\Money;
use Tests\TestCase;

/** PayPal is simulated with the fake of the HTTP client: no test reaches the real service. */
final class PaypalGatewayTest extends TestCase
{
    private const string BASE = 'https://api-m.sandbox.paypal.com';

    private const string ORDERS = 'api-m.sandbox.paypal.com/v2/checkout/orders';

    private const string TOKEN = 'api-m.sandbox.paypal.com/v1/oauth2/token';

    /** The order is created in dollars (3000 colones at 500 per dollar), with the payment id as idempotency key, and waits for the buyer. */
    public function test_charge_creates_the_order_in_dollars_and_waits_for_the_buyer(): void
    {
        $this->fakeToken();
        Http::fake([self::ORDERS => Http::response([
            'id' => 'ORDER1',
            'status' => 'PAYER_ACTION_REQUIRED',
            'intent' => 'CAPTURE',
            'payer' => ['email_address' => 'buyer@example.com'],
            'links' => [['rel' => 'self', 'href' => self::BASE.'/v2/checkout/orders/ORDER1'], ['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER1']],
        ], 201)]);

        $result = $this->gateway()->charge($this->request());

        $this->assertSame(PaymentStatus::Pending, $result->status);
        $this->assertSame('ORDER1', $result->reference->value);
        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token=ORDER1', $result->payload['approval_url']);
        $this->assertSame('6.00', $result->payload['amount_usd']);
        $this->assertArrayNotHasKey('payer', $result->payload, 'Personal data of the buyer is not kept.');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/oauth2/token')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('client:secret'))
            && $request->body() === 'grant_type=client_credentials');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v2/checkout/orders')
            && $request->hasHeader('Authorization', 'Bearer access_token_1')
            && $request->hasHeader('PayPal-Request-Id', '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d55')
            && $request->data()['intent'] === 'CAPTURE'
            && $request->data()['purchase_units'][0]['amount'] === ['currency_code' => 'USD', 'value' => '6.00']
            && $request->data()['purchase_units'][0]['reference_id'] === '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d44'
            && $request->data()['payment_source']['paypal']['experience_context']['return_url'] === 'https://app.test/pago/ok');
    }

    /** The amount in dollars never goes below one cent and is rounded to cents. */
    public function test_the_conversion_rounds_to_cents_with_a_floor_of_one_cent(): void
    {
        $this->fakeToken();
        Http::fake([self::ORDERS => Http::response(['id' => 'ORDER1', 'status' => 'CREATED'], 201)]);

        $this->assertSame('0.01', $this->gateway()->charge($this->request(1))->payload['amount_usd']);
        $this->assertSame('2.00', $this->gateway()->charge($this->request(99999))->payload['amount_usd']);
        $this->assertSame('6.67', $this->gateway(rate: 449.9)->charge($this->request())->payload['amount_usd']);
    }

    /** Without return address the order is created without payment source and the buyer is sent to the approve link. */
    public function test_the_approve_link_is_used_when_there_is_no_return_address(): void
    {
        $this->fakeToken();
        Http::fake([self::ORDERS => Http::response(['id' => 'ORDER1', 'status' => 'CREATED', 'links' => [['rel' => 'approve', 'href' => 'https://sandbox.example/approve']]], 201)]);

        $result = $this->gateway(returnUrl: '')->charge($this->request());

        $this->assertSame('https://sandbox.example/approve', $result->payload['approval_url']);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v2/checkout/orders') && ! array_key_exists('payment_source', $request->data()));
    }

    /** SINPE Móvil does not exist in PayPal: it is refused before any call. */
    public function test_sinpe_movil_is_refused_without_calling_paypal(): void
    {
        Http::fake();

        try {
            $this->gateway()->charge($this->request(method: PaymentMethod::SinpeMovil));
            $this->fail('SINPE Móvil should be refused.');
        } catch (PaymentRejectedException $rejection) {
            $this->assertSame('metodo_no_soportado', $rejection->parameters()['code']);
        }

        Http::assertNothingSent();
    }

    /** An order that the buyer approved is captured when its status is read, and the payment is paid. */
    public function test_reading_an_approved_order_captures_it(): void
    {
        $this->fakeToken();
        Http::fake([
            self::ORDERS.'/ORDER1/capture' => Http::response(['id' => 'ORDER1', 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP1', 'status' => 'COMPLETED']]]]]], 201),
            self::ORDERS.'/ORDER1' => Http::response(['id' => 'ORDER1', 'status' => 'APPROVED']),
        ]);

        $result = $this->gateway()->status(new ChargeReference('ORDER1'));

        $this->assertSame(PaymentStatus::Paid, $result->status);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/ORDER1/capture')
            && $request->method() === 'POST'
            && $request->hasHeader('PayPal-Request-Id', 'capture-ORDER1'));
    }

    /** An order that the buyer has not approved yet stays pending and is not captured. */
    public function test_reading_an_order_that_waits_for_the_buyer_does_not_capture(): void
    {
        $this->fakeToken();
        Http::fake([self::ORDERS.'/ORDER1' => Http::response(['id' => 'ORDER1', 'status' => 'PAYER_ACTION_REQUIRED', 'links' => [['rel' => 'payer-action', 'href' => 'https://sandbox.example/pay']]])]);

        $result = $this->gateway()->status(new ChargeReference('ORDER1'));

        $this->assertSame(PaymentStatus::Pending, $result->status);
        $this->assertSame('https://sandbox.example/pay', $result->payload['approval_url']);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/capture'));
    }

    /** A voided order and a declined capture fail; a capture under review stays pending. */
    public function test_voided_declined_and_reviewed_orders(): void
    {
        $this->fakeToken();
        Http::fake([
            self::ORDERS.'/VOID' => Http::response(['id' => 'VOID', 'status' => 'VOIDED']),
            self::ORDERS.'/DECL' => Http::response(['id' => 'DECL', 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['status' => 'DECLINED']]]]]]),
            self::ORDERS.'/REVW' => Http::response(['id' => 'REVW', 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['status' => 'PENDING']]]]]]),
        ]);

        $voided = $this->gateway()->status(new ChargeReference('VOID'));
        $declined = $this->gateway()->status(new ChargeReference('DECL'));
        $reviewed = $this->gateway()->status(new ChargeReference('REVW'));

        $this->assertSame(PaymentStatus::Failed, $voided->status);
        $this->assertSame('VOIDED', $voided->declineCode);
        $this->assertSame(PaymentStatus::Failed, $declined->status);
        $this->assertSame('DECLINED', $declined->declineCode);
        $this->assertSame(PaymentStatus::Pending, $reviewed->status);
    }

    /** A capture that PayPal refuses because the buyer's source was declined is a rejection. */
    public function test_a_declined_capture_is_a_rejection(): void
    {
        $this->fakeToken();
        Http::fake([
            self::ORDERS.'/ORDER1/capture' => Http::response(['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'INSTRUMENT_DECLINED']]], 422),
            self::ORDERS.'/ORDER1' => Http::response(['id' => 'ORDER1', 'status' => 'APPROVED']),
        ]);

        try {
            $this->gateway()->status(new ChargeReference('ORDER1'));
            $this->fail('The capture should be rejected.');
        } catch (PaymentRejectedException $rejection) {
            $this->assertSame('INSTRUMENT_DECLINED', $rejection->parameters()['code']);
        }
    }

    /** An order that was already captured is read again instead of failing. */
    public function test_an_order_already_captured_is_read_again(): void
    {
        $this->fakeToken();
        Http::fake([
            self::ORDERS.'/ORDER1/capture' => Http::response(['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'ORDER_ALREADY_CAPTURED']]], 422),
            self::ORDERS.'/ORDER1' => Http::sequence()
                ->push(['id' => 'ORDER1', 'status' => 'APPROVED'])
                ->push(['id' => 'ORDER1', 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['status' => 'COMPLETED']]]]]]),
        ]);

        $this->assertSame(PaymentStatus::Paid, $this->gateway()->status(new ChargeReference('ORDER1'))->status);
    }

    /** Without an access token PayPal cannot be used. */
    public function test_a_refused_token_is_unavailability(): void
    {
        Http::fake([self::TOKEN => Http::response(['error' => 'invalid_client'], 401)]);

        $this->assertUnavailable(fn () => $this->gateway()->charge($this->request()));
        Http::assertSentCount(1);
    }

    /** A server error, a body that is not an order or an error that is not a refusal mean the provider cannot be used now. */
    public function test_other_failures_are_unavailability(): void
    {
        Http::fake([
            self::TOKEN => Http::response(['access_token' => 'access_token_1', 'expires_in' => 32400]),
            self::ORDERS => Http::sequence()
                ->push(['name' => 'INTERNAL_SERVER_ERROR'], 500)
                ->push(['status' => 'CREATED'], 201)
                ->push(['name' => 'INVALID_REQUEST', 'details' => [['issue' => 'MALFORMED_REQUEST_JSON']]], 400),
        ]);

        $this->assertUnavailable(fn () => $this->gateway()->charge($this->request()));
        $this->assertUnavailable(fn () => $this->gateway()->charge($this->request()));
        $this->assertUnavailable(fn () => $this->gateway()->charge($this->request()));
    }

    /** If the connection fails the order is not created twice by a retry of the adapter. */
    public function test_a_failed_connection_is_unavailability_and_is_not_repeated(): void
    {
        $this->fakeToken();
        $calls = 0;
        Http::fake([
            self::TOKEN => Http::response(['access_token' => 'access_token_1']),
            self::ORDERS => function () use (&$calls): never {
                $calls++;

                throw new ConnectionException('timeout');
            },
        ]);

        $this->assertUnavailable(fn () => $this->gateway()->charge($this->request()));
        $this->assertSame(1, $calls);
    }

    /** A notification is trusted only when PayPal itself confirms its signature. */
    public function test_a_webhook_is_verified_with_paypal(): void
    {
        $this->fakeToken();
        Http::fake(['api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::sequence()
            ->push(['verification_status' => 'SUCCESS'])
            ->push(['verification_status' => 'FAILURE'])
            ->push([], 500)]);

        $payload = '{"id":"WH-1","event_type":"PAYMENT.CAPTURE.COMPLETED"}';
        $headers = [
            'paypal-auth-algo' => 'SHA256withRSA',
            'paypal-cert-url' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT',
            'paypal-transmission-id' => 'abc',
            'paypal-transmission-sig' => 'sig',
            'paypal-transmission-time' => '2026-10-26T06:00:00Z',
        ];

        $this->assertTrue($this->gateway()->verifyWebhook($payload, $headers));
        $this->assertFalse($this->gateway()->verifyWebhook($payload, $headers));
        $this->assertFalse($this->gateway()->verifyWebhook($payload, $headers), 'A failure while checking is not trusted.');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/verify-webhook-signature')
            && $request->data()['webhook_id'] === 'WH_ID'
            && $request->data()['webhook_event']['id'] === 'WH-1'
            && $request->data()['transmission_id'] === 'abc');
    }

    /** Without webhook id, headers or a valid body the notification is not trusted and PayPal is not asked. */
    public function test_an_incomplete_webhook_is_not_trusted(): void
    {
        Http::fake();

        $this->assertFalse($this->gateway(webhookId: '')->verifyWebhook('{"id":"1"}', ['paypal-transmission-id' => 'abc']));
        $this->assertFalse($this->gateway()->verifyWebhook('{"id":"1"}', []));
        $this->assertFalse($this->gateway()->verifyWebhook('not json', ['paypal-auth-algo' => 'x']));
        Http::assertNothingSent();
    }

    /** Only the sandbox and a positive rate are accepted. */
    public function test_the_live_service_and_a_bad_rate_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PaypalGateway('client', 'secret', 'https://api-m.paypal.com', 500.0);
    }

    /** A rate of zero would divide by zero. */
    public function test_a_rate_that_is_not_positive_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PaypalGateway('client', 'secret', self::BASE, 0.0);
    }

    /** The name of the provider is the one that is stored with each payment. */
    public function test_the_provider_name(): void
    {
        $this->assertSame('paypal', $this->gateway()->provider());
    }

    /** Answer the request of the token. */
    private function fakeToken(): void
    {
        Http::fake([self::TOKEN => Http::response(['access_token' => 'access_token_1', 'token_type' => 'Bearer', 'expires_in' => 32400])]);
    }

    /** A gateway for the sandbox; 500 colones per dollar unless told otherwise. */
    private function gateway(float $rate = 500.0, string $webhookId = 'WH_ID', string $returnUrl = 'https://app.test/pago/ok'): PaypalGateway
    {
        return new PaypalGateway('client', 'secret', self::BASE, $rate, $webhookId, $returnUrl, '');
    }

    /** A charge of 3000 colones, or of the given céntimos. */
    private function request(int $cents = 300000, PaymentMethod $method = PaymentMethod::Card): ChargeRequest
    {
        return new ChargeRequest(
            new PaymentId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d55'),
            new SaleId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d44'),
            new Money($cents),
            $method,
            '',
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
