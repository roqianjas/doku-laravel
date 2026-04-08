<?php

namespace DokuLaravel\Services;

use DokuLaravel\Contracts\CheckoutService;
use DokuLaravel\DTO\CheckoutResult;
use DokuLaravel\DTO\CreateCheckoutData;
use DokuLaravel\Support\DokuConfig;
use Illuminate\Support\Str;

class FakeCheckoutService implements CheckoutService
{
    public function __construct(
        protected DokuConfig $config,
    ) {
    }

    public function createCheckout(CreateCheckoutData $data): CheckoutResult
    {
        $requestId = (string) Str::uuid();
        $providerReference = $data->orderNumber;

        return new CheckoutResult(
            success: true,
            requestId: $requestId,
            providerReference: $providerReference,
            paymentUrl: $this->config->fakeCheckoutBaseUrl().'/'.rawurlencode($data->orderNumber),
            status: 'pending',
            expiresAt: now()->addMinutes($data->paymentDueDate)->toIso8601String(),
            raw: [
                'driver' => 'fake',
                'request_id' => $requestId,
                'message' => 'Fake checkout generated for local demo.',
            ],
        );
    }
}
