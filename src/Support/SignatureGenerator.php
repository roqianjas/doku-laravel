<?php

namespace DokuLaravel\Support;

class SignatureGenerator
{
    public function generateDigest(string $body): string
    {
        return base64_encode(hash('sha256', $body, true));
    }

    public function generateSignature(
        string $clientId,
        string $requestId,
        string $requestTimestamp,
        string $requestTarget,
        string $secretKey,
        ?string $digest = null,
    ): string {
        $components = [
            "Client-Id:{$clientId}",
            "Request-Id:{$requestId}",
            "Request-Timestamp:{$requestTimestamp}",
            "Request-Target:{$requestTarget}",
        ];

        if ($digest !== null && $digest !== '') {
            $components[] = "Digest:{$digest}";
        }

        $signature = base64_encode(hash_hmac('sha256', implode("\n", $components), $secretKey, true));

        return 'HMACSHA256='.$signature;
    }

    public function verifySignature(
        string $signature,
        string $clientId,
        string $requestId,
        string $requestTimestamp,
        string $requestTarget,
        string $secretKey,
        ?string $digest = null,
    ): bool {
        return hash_equals(
            $this->generateSignature($clientId, $requestId, $requestTimestamp, $requestTarget, $secretKey, $digest),
            $signature,
        );
    }
}
