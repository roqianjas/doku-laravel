<?php

namespace DokuLaravel\Support;

use DokuLaravel\Exceptions\ConfigurationException;
use Illuminate\Contracts\Config\Repository;

class DokuConfig
{
    public function __construct(
        protected Repository $config,
    ) {
    }

    public function driver(): string
    {
        return (string) $this->config->get('doku.driver', 'fake');
    }

    public function environment(): string
    {
        return (string) $this->config->get('doku.environment', 'sandbox');
    }

    public function clientId(): string
    {
        return $this->required('doku.client_id');
    }

    public function secretKey(): string
    {
        return $this->required('doku.secret_key');
    }

    public function baseUrl(): string
    {
        $configured = (string) $this->config->get('doku.base_url', '');

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return $this->environment() === 'production'
            ? 'https://api.doku.com'
            : 'https://api-sandbox.doku.com';
    }

    public function paymentDueDate(): int
    {
        return (int) $this->config->get('doku.payment_due_date', 60);
    }

    public function autoRedirect(): bool
    {
        return (bool) $this->config->get('doku.auto_redirect', true);
    }

    /**
     * @return array<int, string>
     */
    public function paymentMethodTypes(): array
    {
        return array_values(array_filter((array) $this->config->get('doku.payment_method_types', [])));
    }

    public function timeout(): int
    {
        return (int) $this->config->get('doku.request_timeout', 20);
    }

    public function validateResponseSignature(): bool
    {
        return (bool) $this->config->get('doku.validate_response_signature', false);
    }

    public function fakeCheckoutBaseUrl(): string
    {
        return rtrim((string) $this->config->get('doku.fake.checkout_base_url'), '/');
    }

    protected function required(string $key): string
    {
        $value = (string) $this->config->get($key, '');

        if ($value === '') {
            throw new ConfigurationException("Missing required DOKU configuration [{$key}].");
        }

        return $value;
    }
}
