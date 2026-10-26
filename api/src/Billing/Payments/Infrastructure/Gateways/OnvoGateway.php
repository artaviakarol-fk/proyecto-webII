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
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Throwable;

/**
 * ONVO Pay through its REST API, with the HTTP client of Laravel and no SDK.
 *
 * Both ways of paying (card and SINPE Móvil) follow the same flow: the client creates the payment method with the
 * publishable key, and this adapter creates the payment intent and confirms it with the secret key. A card payment
 * ends at once; a SINPE Móvil one stays in processing until the transfer arrives.
 */
final readonly class OnvoGateway implements PaymentGateway
{
    public const string PROVIDER = 'onvo';

    private const int TIMEOUT_SECONDS = 10;

    private const int STATUS_ATTEMPTS = 3;

    private const int STATUS_PAUSE_MILLISECONDS = 200;

    /** Only test keys are accepted while the course lasts: no real money is ever charged. */
    private const string TEST_KEY_PREFIX = 'onvo_test_';

    /** Fields of the intent that are kept; the payment method, the charges and the customer carry personal data and are left out. */
    private const array KEPT_FIELDS = ['id', 'status', 'amount', 'currency', 'description', 'metadata', 'createdAt'];

    /** Reject any key that is not a test key. */
    public function __construct(
        private string $secretKey,
        private string $baseUrl,
        private string $webhookSecret = '',
    ) {
        if (! str_starts_with($secretKey, self::TEST_KEY_PREFIX)) {
            throw new InvalidArgumentException('ONVO only accepts test keys (onvo_test_...) in this project.');
        }
    }

    /** Name of the provider. */
    public function provider(): string
    {
        return self::PROVIDER;
    }

    /**
     * Create the payment intent in colones and confirm it with the payment method the client created.
     * The amount goes in céntimos, which is the smallest unit of the colón for ONVO. A call is not repeated
     * when the connection fails, because the provider may have taken it and a repeat would charge twice.
     */
    public function charge(ChargeRequest $request): ChargeResult
    {
        $intent = $this->send(fn (PendingRequest $http): Response => $http->post('/payment-intents', [
            'amount' => $request->amount->cents,
            'currency' => 'CRC',
            'description' => $request->description,
            'metadata' => ['paymentId' => $request->paymentId->value, 'saleId' => $request->saleId->value],
        ]));

        $intentId = $this->text($intent['id'] ?? null) ?? throw GatewayUnavailableException::create();

        $confirmed = $this->send(fn (PendingRequest $http): Response => $http->post(
            '/payment-intents/'.rawurlencode($intentId).'/confirm',
            ['paymentMethodId' => $request->methodToken],
        ));

        return $this->resultOf($this->withDeclineReason($confirmed, $intentId));
    }

    /**
     * ONVO answers a declined card with success and the intent back at requires_payment_method; the reason is only in
     * lastPaymentError of the intent. If the confirmation does not carry it, the intent is read once more. If that read
     * fails the answer is kept as it is: the payment is failed anyway.
     *
     * @param  array<string, mixed>  $confirmed
     * @return array<string, mixed>
     */
    private function withDeclineReason(array $confirmed, string $intentId): array
    {
        if (($confirmed['status'] ?? null) !== 'requires_payment_method' || is_array($confirmed['lastPaymentError'] ?? null)) {
            return $confirmed;
        }

        try {
            return $this->send(fn (PendingRequest $http): Response => $http->get('/payment-intents/'.rawurlencode($intentId)));
        } catch (GatewayUnavailableException) {
            return $confirmed;
        }
    }

    /** Ask ONVO for the payment intent; a read can be repeated when the connection fails. */
    public function status(ChargeReference $reference): ChargeResult
    {
        return $this->resultOf($this->send(fn (PendingRequest $http): Response => $http
            ->retry(self::STATUS_ATTEMPTS, self::STATUS_PAUSE_MILLISECONDS, fn (Throwable $error): bool => $error instanceof ConnectionException)
            ->get('/payment-intents/'.rawurlencode($reference->value))));
    }

    /** ONVO sends the secret of the webhook in the X-Webhook-Secret header; it is compared in constant time. */
    public function verifyWebhook(string $payload, array $headers): bool
    {
        $given = $headers['x-webhook-secret'] ?? '';

        return $this->webhookSecret !== '' && $given !== '' && hash_equals($this->webhookSecret, $given);
    }

    /** A client with the secret key, the base address and the time limit of every call. */
    private function http(): PendingRequest
    {
        return Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout(self::TIMEOUT_SECONDS);
    }

    /**
     * Make a call and answer its body; a refusal of the bank is a rejection and any other failure is unavailability.
     *
     * @param  Closure(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(Closure $call): array
    {
        try {
            $response = $call($this->http());
        } catch (ConnectionException) {
            Log::warning('ONVO did not answer.');

            throw GatewayUnavailableException::create();
        }

        if ($response->successful()) {
            $body = $response->json();

            return is_array($body) ? $body : throw GatewayUnavailableException::create();
        }

        throw $this->failure($response);
    }

    /** A refused card (an error with a cards.* code) is a rejection; anything else means the provider cannot be used now. */
    private function failure(Response $response): PaymentRejectedException|GatewayUnavailableException
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $code = $this->text($body['code'] ?? null);

        Log::warning('ONVO answered with an error.', ['status' => $response->status(), 'code' => $code]);

        if ($response->status() === 400 && $code !== null && str_starts_with($code, 'cards.')) {
            $details = is_array($body['details'] ?? null) ? $body['details'] : [];
            $card = is_array($details['card'] ?? null) ? $details['card'] : [];

            return PaymentRejectedException::create($this->text($card['declineCode'] ?? null) ?? $this->text($card['reason'] ?? null) ?? $code);
        }

        return GatewayUnavailableException::create();
    }

    /**
     * Translate a payment intent of ONVO: succeeded is paid; canceled or back to requires_payment_method is failed;
     * processing and requires_action are still pending.
     *
     * @param  array<string, mixed>  $intent
     */
    private function resultOf(array $intent): ChargeResult
    {
        $id = $this->text($intent['id'] ?? null) ?? throw GatewayUnavailableException::create();
        $state = $this->text($intent['status'] ?? null);

        $status = match ($state) {
            'succeeded' => PaymentStatus::Paid,
            'canceled', 'requires_payment_method' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new ChargeResult($status, new ChargeReference($id), $status === PaymentStatus::Failed ? $this->declineCodeOf($intent) ?? $state : null, array_intersect_key($intent, array_flip(self::KEPT_FIELDS)));
    }

    /**
     * The code that ONVO gives for the last error of the payment, if there is one.
     *
     * @param  array<string, mixed>  $intent
     */
    private function declineCodeOf(array $intent): ?string
    {
        $error = $intent['lastPaymentError'] ?? null;

        return is_array($error) ? $this->text($error['code'] ?? null) : null;
    }

    /** A non-empty text, or null. */
    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
