<?php

namespace DokuLaravel\Services;

use DokuLaravel\Contracts\StatusService;
use DokuLaravel\DTO\StatusResult;
use Illuminate\Support\Str;

class FakeStatusService implements StatusService
{
    public function checkStatus(string $reference): StatusResult
    {
        return new StatusResult(
            success: true,
            requestId: (string) Str::uuid(),
            reference: $reference,
            providerStatus: 'PENDING',
            normalizedStatus: 'pending',
            paymentMethod: 'FAKE_CHECKOUT',
            amount: null,
            raw: [
                'driver' => 'fake',
                'message' => 'Fake driver always returns pending. Final state is controlled by the local sandbox screen.',
            ],
        );
    }
}
