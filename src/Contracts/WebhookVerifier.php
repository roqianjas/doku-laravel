<?php

namespace DokuLaravel\Contracts;

use DokuLaravel\DTO\NotificationData;

interface WebhookVerifier
{
    /**
     * @param  array<string, string|array<int, string>>  $headers
     */
    public function parseAndVerify(array $headers, string $body, string $requestTarget): NotificationData;
}
