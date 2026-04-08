<?php

namespace DokuLaravel\Tests\Unit;

use DokuLaravel\Support\SignatureGenerator;
use PHPUnit\Framework\TestCase;

class SignatureGeneratorTest extends TestCase
{
    public function test_it_can_round_trip_a_non_snap_signature(): void
    {
        $generator = new SignatureGenerator;

        $body = json_encode([
            'order' => [
                'invoice_number' => 'ORD-20260408-0001',
                'amount' => 149000,
            ],
        ], JSON_THROW_ON_ERROR);

        $digest = $generator->generateDigest($body);
        $signature = $generator->generateSignature(
            clientId: 'demo-client',
            requestId: 'req-001',
            requestTimestamp: '2026-04-08T02:30:00Z',
            requestTarget: '/webhooks/doku',
            secretKey: 'super-secret',
            digest: $digest,
        );

        $this->assertTrue($generator->verifySignature(
            signature: $signature,
            clientId: 'demo-client',
            requestId: 'req-001',
            requestTimestamp: '2026-04-08T02:30:00Z',
            requestTarget: '/webhooks/doku',
            secretKey: 'super-secret',
            digest: $digest,
        ));
    }
}
