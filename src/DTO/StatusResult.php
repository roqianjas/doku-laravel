<?php

namespace DokuLaravel\DTO;

readonly class StatusResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $success,
        public string $requestId,
        public string $reference,
        public ?string $providerStatus,
        public string $normalizedStatus,
        public ?string $paymentMethod,
        public ?int $amount,
        public array $raw = [],
    ) {
    }
}
