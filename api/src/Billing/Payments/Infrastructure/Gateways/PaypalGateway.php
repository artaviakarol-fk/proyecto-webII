<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Gateways;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\Exceptions\PaymentRejectedException;
use Src\Billing\Payments\Domain\Exceptions\RefundNotSupportedException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Billing\Payments\Domain\ValueObjects\RefundResult;
use Src\Shared\Domain\ValueObjects\Money;
use Throwable;

/**
 * PayPal through the Orders v2 REST API, with the HTTP client of Laravel and no SDK.
 *
 * PayPal does not charge colones, so the amount is converted to US dollars with a rate that comes from the
 * configuration. The buyer pays on a page of PayPal: charging creates the order and answers the address where the buyer
 * approves it (the payment stays pending); when the status is read after the approval, the order is captured.
 */
final readonly class PaypalGateway implements PaymentGateway
{
    public const string PROVIDER = 'paypal';

    private const int TIMEOUT_SECONDS = 10;

    private const int STATUS_ATTEMPTS = 3;

    private const int STATUS_PAUSE_MILLISECONDS = 200;

    /** Only the sandbox is accepted while the course lasts: no real money is ever charged. */
    private const string SANDBOX_HOST = 'api-m.sandbox.paypal.com';

    /** Fields of the order that are kept; the buyer and the shipping data are personal and are left out. */
    private const array KEPT_FIELDS = ['id', 'status', 'intent', 'create_time', 'update_time'];

    /** Errors of the capture that mean that the buyer's money source refused the payment. */
    private const array DECLINED_ISSUES = ['INSTRUMENT_DECLINED', 'PAYER_ACTION_REQUIRED', 'TRANSACTION_REFUSED', 'PAYER_CANNOT_PAY'];

    /** Reject any address that is not the sandbox and any rate that is not positive. */
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $baseUrl,
        private float $colonesPerDollar,
        private string $webhookId = '',
        private string $returnUrl = '',
        private string $cancelUrl = '',
    ) {
        if (parse_url($baseUrl, PHP_URL_HOST) !== self::SANDBOX_HOST) {
            throw new InvalidArgumentException('PayPal only accepts the sandbox ('.self::SANDBOX_HOST.') in this project.');
        }

        if ($colonesPerDollar <= 0) {
            throw new InvalidArgumentException('The exchange rate of the colón must be positive.');
        }
    }

    /** Name of the provider. */
    public function provider(): string
    {
        return self::PROVIDER;
    }

    /** The buyer chooses how to pay on the page of PayPal: the client sends nothing. */
    public function requiresClientMethod(): bool
    {
        return false;
    }

    /** PayPal charges a card or the PayPal account on its own page; it has no SINPE Móvil. */
    public function supportedMethods(): array
    {
        return [PaymentMethod::Card];
    }

    /**
     * Refunds are not made from this system with PayPal. The way would be POST /v2/payments/captures/{capture_id}/refund
     * with the id of the capture (not of the order) and a PayPal-Request-Id; it was left out because the capture id is
     * not stored yet, the amount would come back in dollars converted with a rate that changes, and the enunciado only
     * asks for it with ONVO. The refund is made from the PayPal dashboard, and the reason is in docs/investigacion-1.md.
     */
    public function refund(ChargeReference $reference, Money $amount): RefundResult
    {
        throw RefundNotSupportedException::for(self::PROVIDER);
    }

    /**
     * Create the order in dollars for the amount of the sale. The id of the payment is the idempotency key, so a
     * repeated call never creates two orders. SINPE Móvil does not exist in PayPal: it is refused.
     */
    public function charge(ChargeRequest $request): ChargeResult
    {
        if ($request->method !== PaymentMethod::Card) {
            throw PaymentRejectedException::create('metodo_no_soportado');
        }

        $dollars = $this->dollars($request->amount->cents);

        $order = $this->send(fn (PendingRequest $http): Response => $http
            ->withHeaders(['PayPal-Request-Id' => $request->paymentId->value])
            ->post('/v2/checkout/orders', array_filter([
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $request->saleId->value,
                    'custom_id' => $request->paymentId->value,
                    'description' => $request->description,
                    'amount' => ['currency_code' => 'USD', 'value' => $dollars],
                ]],
                'payment_source' => $this->returnUrl === '' ? null : ['paypal' => ['experience_context' => [
                    'return_url' => $this->returnUrl,
                    'cancel_url' => $this->cancelUrl !== '' ? $this->cancelUrl : $this->returnUrl,
                    'user_action' => 'PAY_NOW',
                ]]],
            ])));

        return $this->resultOf($order, ['amount_usd' => $dollars, 'colones_per_dollar' => $this->colonesPerDollar]);
    }

    /**
     * Ask PayPal for the order. A read can be repeated when the connection fails. An order the buyer already approved
     * is captured now, and the answer is the result of the capture.
     */
    public function status(ChargeReference $reference): ChargeResult
    {
        $order = $this->send(fn (PendingRequest $http): Response => $http
            ->retry(self::STATUS_ATTEMPTS, self::STATUS_PAUSE_MILLISECONDS, fn (Throwable $error): bool => $error instanceof ConnectionException)
            ->get('/v2/checkout/orders/'.rawurlencode($reference->value)));

        if ($this->text($order['status'] ?? null) !== 'APPROVED') {
            return $this->resultOf($order);
        }

        return $this->capture($reference);
    }

    /**
     * PayPal signs its notifications; the signature is checked by asking PayPal itself with the id of the webhook.
     * Any failure while checking means the notification is not trusted.
     */
    public function verifyWebhook(string $payload, array $headers): bool
    {
        $event = json_decode($payload, true);
        $required = ['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'];

        if ($this->webhookId === '' || ! is_array($event) || array_diff($required, array_keys(array_filter($headers))) !== []) {
            return false;
        }

        try {
            $answer = $this->send(fn (PendingRequest $http): Response => $http->post('/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $headers['paypal-auth-algo'],
                'cert_url' => $headers['paypal-cert-url'],
                'transmission_id' => $headers['paypal-transmission-id'],
                'transmission_sig' => $headers['paypal-transmission-sig'],
                'transmission_time' => $headers['paypal-transmission-time'],
                'webhook_id' => $this->webhookId,
                'webhook_event' => $event,
            ]));
        } catch (GatewayUnavailableException|PaymentRejectedException) {
            return false;
        }

        return ($answer['verification_status'] ?? null) === 'SUCCESS';
    }

    /**
     * Capture the approved order. The capture of an order is idempotent for PayPal with its own request id, and an order that
     * was already captured is read again instead of failing.
     */
    private function capture(ChargeReference $reference): ChargeResult
    {
        try {
            $order = $this->send(fn (PendingRequest $http): Response => $http
                ->withHeaders(['PayPal-Request-Id' => 'capture-'.$reference->value])
                ->withBody('', 'application/json')
                ->post('/v2/checkout/orders/'.rawurlencode($reference->value).'/capture'));
        } catch (PaymentRejectedException $rejection) {
            if (($rejection->parameters()['code'] ?? '') === 'ORDER_ALREADY_CAPTURED') {
                return $this->resultOf($this->send(fn (PendingRequest $http): Response => $http->get('/v2/checkout/orders/'.rawurlencode($reference->value))));
            }

            throw $rejection;
        }

        return $this->resultOf($order);
    }

    /** Colones in céntimos to a decimal string of US dollars, with two decimals and at least one cent. */
    private function dollars(int $cents): string
    {
        return number_format(max(1, (int) round($cents / $this->colonesPerDollar)) / 100, 2, '.', '');
    }

    /** The token of the application, asked every time: it keeps the adapter without state. */
    private function token(): string
    {
        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->baseUrl($this->baseUrl)
                ->acceptJson()
                ->asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException) {
            Log::warning('PayPal did not answer the token request.');

            throw GatewayUnavailableException::create();
        }

        $token = $response->successful() ? $response->json('access_token') : null;

        if (! is_string($token) || $token === '') {
            Log::warning('PayPal did not give an access token.', ['status' => $response->status()]);

            throw GatewayUnavailableException::create();
        }

        return $token;
    }

    /**
     * Make a call and answer its body; a refusal of the buyer's source is a rejection and any other failure is unavailability.
     *
     * @param  Closure(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(Closure $call): array
    {
        $http = Http::withToken($this->token())
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout(self::TIMEOUT_SECONDS);

        try {
            $response = $call($http);
        } catch (ConnectionException) {
            Log::warning('PayPal did not answer.');

            throw GatewayUnavailableException::create();
        }

        if ($response->successful()) {
            $body = $response->json();

            return is_array($body) ? $body : throw GatewayUnavailableException::create();
        }

        throw $this->failure($response);
    }

    /** A 422 with a known issue is a rejection; anything else means the provider cannot be used now. */
    private function failure(Response $response): PaymentRejectedException|GatewayUnavailableException
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $issue = $this->issueOf($body);

        Log::warning('PayPal answered with an error.', ['status' => $response->status(), 'issue' => $issue, 'name' => $this->text($body['name'] ?? null)]);

        if ($response->status() === 422 && $issue !== null && ($issue === 'ORDER_ALREADY_CAPTURED' || in_array($issue, self::DECLINED_ISSUES, true))) {
            return PaymentRejectedException::create($issue);
        }

        return GatewayUnavailableException::create();
    }

    /**
     * The first issue of an error of PayPal, which is the most precise code it gives.
     *
     * @param  array<string, mixed>  $body
     */
    private function issueOf(array $body): ?string
    {
        $details = is_array($body['details'] ?? null) ? $body['details'] : [];
        $first = is_array($details[0] ?? null) ? $details[0] : [];

        return $this->text($first['issue'] ?? null);
    }

    /**
     * Translate an order of PayPal: COMPLETED is paid unless its capture is declined or still under review;
     * VOIDED is failed; every other state (created, waiting for the buyer, approved) is pending.
     *
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $extra  Data that PayPal does not give back and that is worth keeping.
     */
    private function resultOf(array $order, array $extra = []): ChargeResult
    {
        $id = $this->text($order['id'] ?? null) ?? throw GatewayUnavailableException::create();
        $state = $this->text($order['status'] ?? null);
        $capture = $this->captureStatus($order);

        $status = match (true) {
            $state === 'VOIDED', $capture === 'DECLINED', $capture === 'FAILED' => PaymentStatus::Failed,
            $state === 'COMPLETED' && ($capture === 'COMPLETED' || $capture === null) => PaymentStatus::Paid,
            default => PaymentStatus::Pending,
        };

        $payload = array_intersect_key($order, array_flip(self::KEPT_FIELDS)) + $extra;
        $url = $this->approvalUrl($order);

        if ($url !== null && $status === PaymentStatus::Pending) {
            $payload['approval_url'] = $url;
        }

        return new ChargeResult($status, new ChargeReference($id), $status === PaymentStatus::Failed ? ($capture ?? $state) : null, $payload);
    }

    /**
     * Status of the first capture of the order, if it has one.
     *
     * @param  array<string, mixed>  $order
     */
    private function captureStatus(array $order): ?string
    {
        $units = is_array($order['purchase_units'] ?? null) ? $order['purchase_units'] : [];
        $unit = is_array($units[0] ?? null) ? $units[0] : [];
        $payments = is_array($unit['payments'] ?? null) ? $unit['payments'] : [];
        $captures = is_array($payments['captures'] ?? null) ? $payments['captures'] : [];
        $first = is_array($captures[0] ?? null) ? $captures[0] : [];

        return $this->text($first['status'] ?? null);
    }

    /**
     * The address where the buyer approves the order.
     *
     * @param  array<string, mixed>  $order
     */
    private function approvalUrl(array $order): ?string
    {
        $links = is_array($order['links'] ?? null) ? $order['links'] : [];

        foreach ($links as $link) {
            if (is_array($link) && in_array($link['rel'] ?? null, ['payer-action', 'approve'], true)) {
                return $this->text($link['href'] ?? null);
            }
        }

        return null;
    }

    /** A non-empty text, or null. */
    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
