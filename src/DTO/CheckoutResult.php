<?php

namespace DokuLaravel\DTO;

readonly class CheckoutResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $success,
        public string $requestId,
        public string $providerReference,
        public string $paymentUrl,
        public string $status,
        public ?string $expiresAt,
        public array $raw = [],
    ) {
    }
}
