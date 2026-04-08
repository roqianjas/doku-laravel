<?php

namespace DokuLaravel\Services;

use DokuLaravel\Contracts\WebhookVerifier;
use DokuLaravel\DTO\NotificationData;
use DokuLaravel\Exceptions\SignatureVerificationException;
use DokuLaravel\Support\DokuConfig;
use DokuLaravel\Support\SignatureGenerator;
use DokuLaravel\Support\StatusNormalizer;
use Illuminate\Support\Arr;

class HttpWebhookVerifier implements WebhookVerifier
{
    public function __construct(
        protected DokuConfig $config,
        protected SignatureGenerator $signatureGenerator,
        protected StatusNormalizer $statusNormalizer,
    ) {
    }

    public function parseAndVerify(array $headers, string $body, string $requestTarget): NotificationData
    {
        $normalizedHeaders = $this->normalizeHeaders($headers);
        $clientId = $normalizedHeaders['client-id'] ?? null;
        $requestId = $normalizedHeaders['request-id'] ?? null;
        $requestTimestamp = $normalizedHeaders['request-timestamp'] ?? null;
        $signature = $normalizedHeaders['signature'] ?? null;

        if (! $clientId || ! $requestId || ! $requestTimestamp || ! $signature) {
            throw new SignatureVerificationException('Missing required DOKU notification headers.');
        }

        if ($clientId !== $this->config->clientId()) {
            throw new SignatureVerificationException('Unexpected DOKU client id.');
        }

        $digest = $this->signatureGenerator->generateDigest($body);
        $isValid = $this->signatureGenerator->verifySignature(
            $signature,
            $clientId,
            $requestId,
            $requestTimestamp,
            $requestTarget,
            $this->config->secretKey(),
            $digest,
        );

        if (! $isValid) {
            throw new SignatureVerificationException('Invalid DOKU notification signature.');
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            throw new SignatureVerificationException('Invalid DOKU notification payload.');
        }

        $providerStatus = Arr::get($payload, 'transaction.status');

        return new NotificationData(
            verified: true,
            clientId: $clientId,
            requestId: $requestId,
            requestTimestamp: $requestTimestamp,
            orderNumber: (string) Arr::get($payload, 'order.invoice_number', ''),
            originalRequestId: Arr::get($payload, 'transaction.original_request_id'),
            providerStatus: is_string($providerStatus) ? $providerStatus : null,
            normalizedStatus: $this->statusNormalizer->normalize(is_string($providerStatus) ? $providerStatus : null),
            paymentMethod: Arr::get($payload, 'channel.id'),
            amount: ($amount = Arr::get($payload, 'order.amount')) !== null ? (int) $amount : null,
            payload: $payload,
        );
    }

    /**
     * @param  array<string, string|array<int, string>>  $headers
     * @return array<string, string>
     */
    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = is_array($value) ? (string) reset($value) : (string) $value;
        }

        return $normalized;
    }
}
