<?php

namespace DokuLaravel\Support;

class StatusNormalizer
{
    public function normalize(?string $providerStatus): string
    {
        return match (strtoupper((string) $providerStatus)) {
            'SUCCESS' => 'paid',
            'PENDING', 'REDIRECT', 'TIMEOUT' => 'pending',
            'FAILED' => 'failed',
            'EXPIRED' => 'expired',
            'CANCELLED', 'CANCELED' => 'cancelled',
            'REFUNDED' => 'refunded',
            default => 'unknown',
        };
    }
}
