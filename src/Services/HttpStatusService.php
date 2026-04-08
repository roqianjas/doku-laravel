<?php

namespace DokuLaravel\Services;

use DokuLaravel\Contracts\StatusService;
use DokuLaravel\DTO\StatusResult;
use DokuLaravel\Exceptions\GatewayRequestException;
use DokuLaravel\Support\DokuConfig;
use DokuLaravel\Support\SignatureGenerator;
use DokuLaravel\Support\StatusNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HttpStatusService implements StatusService
{
    public function __construct(
        protected DokuConfig $config,
        protected SignatureGenerator $signatureGenerator,
        protected StatusNormalizer $statusNormalizer,
    ) {
    }

    public function checkStatus(string $reference): StatusResult
    {
        $requestTarget = '/orders/v1/status/'.rawurlencode($reference);
        $requestId = (string) Str::uuid();
        $timestamp = now('UTC')->toIso8601ZuluString();

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
            ),
            'Accept' => 'application/json',
        ];

        $response = Http::timeout($this->config->timeout())
            ->withHeaders($headers)
            ->get($this->config->baseUrl().$requestTarget);

        if (! $response->successful()) {
            throw new GatewayRequestException(
                sprintf('DOKU status request failed with HTTP %s: %s', $response->status(), $response->body())
            );
        }

        $decoded = $response->json();
        $providerStatus = Arr::get($decoded, 'transaction.status');

        return new StatusResult(
            success: true,
            requestId: $requestId,
            reference: (string) Arr::get($decoded, 'order.invoice_number', $reference),
            providerStatus: is_string($providerStatus) ? $providerStatus : null,
            normalizedStatus: $this->statusNormalizer->normalize(is_string($providerStatus) ? $providerStatus : null),
            paymentMethod: Arr::get($decoded, 'channel.id'),
            amount: ($amount = Arr::get($decoded, 'order.amount')) !== null ? (int) $amount : null,
            raw: is_array($decoded) ? $decoded : ['body' => $response->body()],
        );
    }
}
