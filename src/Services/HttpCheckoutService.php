<?php

namespace DokuLaravel\Services;

use DokuLaravel\Contracts\CheckoutService;
use DokuLaravel\DTO\CheckoutResult;
use DokuLaravel\DTO\CreateCheckoutData;
use DokuLaravel\Exceptions\GatewayRequestException;
use DokuLaravel\Support\DokuConfig;
use DokuLaravel\Support\SignatureGenerator;
use DokuLaravel\Support\StatusNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HttpCheckoutService implements CheckoutService
{
    public function __construct(
        protected DokuConfig $config,
        protected SignatureGenerator $signatureGenerator,
        protected StatusNormalizer $statusNormalizer,
    ) {
    }

    public function createCheckout(CreateCheckoutData $data): CheckoutResult
    {
        $requestTarget = '/checkout/v1/payment';
        $requestId = (string) Str::uuid();
        $timestamp = now('UTC')->toIso8601ZuluString();
        $payload = $this->buildPayload($data);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            throw new GatewayRequestException('Unable to encode checkout request body.');
        }

        $digest = $this->signatureGenerator->generateDigest($body);
        $headers = [
            'Client-Id' => $this->config->clientId(),
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => $this->signatureGenerator->generateSignature(
                $this->config->clientId(),
                $requestId,
                $timestamp,
                $requestTarget,
                $this->config->secretKey(),
                $digest,
            ),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $response = Http::timeout($this->config->timeout())
            ->withHeaders($headers)
            ->withBody($body, 'application/json')
            ->post($this->config->baseUrl().$requestTarget);

        if (! $response->successful()) {
            throw new GatewayRequestException(
                sprintf('DOKU checkout request failed with HTTP %s: %s', $response->status(), $response->body())
            );
        }

        $decoded = $response->json();
        $paymentUrl = (string) Arr::get($decoded, 'response.payment.url', '');
        $providerReference = (string) Arr::get($decoded, 'response.payment.token_id', $data->orderNumber);

        if ($paymentUrl === '') {
            throw new GatewayRequestException('DOKU checkout response did not include payment URL.');
        }

        return new CheckoutResult(
            success: true,
            requestId: $requestId,
            providerReference: $providerReference,
            paymentUrl: $paymentUrl,
            status: $this->statusNormalizer->normalize('PENDING'),
            expiresAt: Arr::get($decoded, 'response.payment.expired_date'),
            raw: is_array($decoded) ? $decoded : ['body' => $response->body()],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(CreateCheckoutData $data): array
    {
        $order = array_filter([
            'amount' => $data->amount,
            'invoice_number' => $data->orderNumber,
            'currency' => $data->currency,
            'callback_url' => $data->callbackUrl,
            'callback_url_result' => $data->callbackUrlResult ?? $data->callbackUrl,
            'auto_redirect' => $data->autoRedirect,
            'line_items' => $data->lineItems,
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== []);

        $paymentMethodTypes = $data->paymentMethodTypes !== []
            ? $data->paymentMethodTypes
            : $this->config->paymentMethodTypes();

        $payment = array_filter([
            'payment_due_date' => $data->paymentDueDate > 0 ? $data->paymentDueDate : $this->config->paymentDueDate(),
            'payment_method_types' => $paymentMethodTypes,
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== []);

        $customer = array_filter([
            'name' => $data->customerName,
            'email' => $data->customerEmail,
        ], static fn ($value): bool => $value !== null && $value !== '');

        $additionalInfo = $data->additionalInfo;

        if ($data->notificationUrl) {
            $additionalInfo['override_notification_url'] = $data->notificationUrl;
        }

        return array_filter([
            'order' => $order,
            'payment' => $payment,
            'customer' => $customer,
            'additional_info' => $additionalInfo,
        ], static fn ($value): bool => $value !== []);
    }
}
