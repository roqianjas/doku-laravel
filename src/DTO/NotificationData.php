<?php

namespace DokuLaravel\DTO;

readonly class NotificationData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public bool $verified,
        public string $clientId,
        public string $requestId,
        public string $requestTimestamp,
        public string $orderNumber,
        public ?string $originalRequestId,
        public ?string $providerStatus,
        public string $normalizedStatus,
        public ?string $paymentMethod,
        public ?int $amount,
        public array $payload,
    ) {
    }
}
