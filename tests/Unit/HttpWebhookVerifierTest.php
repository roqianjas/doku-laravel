<?php

namespace DokuLaravel\Tests\Unit;

use DokuLaravel\Exceptions\SignatureVerificationException;
use DokuLaravel\Services\HttpWebhookVerifier;
use DokuLaravel\Support\DokuConfig;
use DokuLaravel\Support\SignatureGenerator;
use DokuLaravel\Support\StatusNormalizer;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;

class HttpWebhookVerifierTest extends TestCase
{
    public function test_it_returns_verified_notification_data(): void
    {
        $config = new Repository([
            'doku' => [
                'client_id' => 'demo-client',
                'secret_key' => 'super-secret',
            ],
        ]);

        $generator = new SignatureGenerator;
        $body = json_encode([
            'order' => [
                'invoice_number' => 'ORD-20260408-0002',
                'amount' => 275000,
            ],
            'transaction' => [
                'status' => 'SUCCESS',
                'original_request_id' => 'req-original-001',
            ],
            'channel' => [
                'id' => 'VIRTUAL_ACCOUNT_BCA',
            ],
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Client-Id' => 'demo-client',
            'Request-Id' => 'notif-001',
            'Request-Timestamp' => '2026-04-08T02:35:00Z',
            'Signature' => $generator->generateSignature(
                clientId: 'demo-client',
                requestId: 'notif-001',
                requestTimestamp: '2026-04-08T02:35:00Z',
                requestTarget: '/webhooks/doku',
                secretKey: 'super-secret',
                digest: $generator->generateDigest($body),
            ),
        ];

        $verifier = new HttpWebhookVerifier(
            new DokuConfig($config),
            $generator,
            new StatusNormalizer,
        );

        $notification = $verifier->parseAndVerify(
            headers: $headers,
            body: $body,
            requestTarget: '/webhooks/doku',
        );

        $this->assertTrue($notification->verified);
        $this->assertSame('ORD-20260408-0002', $notification->orderNumber);
        $this->assertSame('paid', $notification->normalizedStatus);
        $this->assertSame('VIRTUAL_ACCOUNT_BCA', $notification->paymentMethod);
        $this->assertSame(275000, $notification->amount);
    }

    public function test_it_rejects_unexpected_client_id(): void
    {
        $config = new Repository([
            'doku' => [
                'client_id' => 'expected-client',
                'secret_key' => 'super-secret',
            ],
        ]);

        $generator = new SignatureGenerator;
        $body = json_encode([
            'order' => [
                'invoice_number' => 'ORD-20260408-0003',
            ],
            'transaction' => [
                'status' => 'SUCCESS',
            ],
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Client-Id' => 'different-client',
            'Request-Id' => 'notif-002',
            'Request-Timestamp' => '2026-04-08T02:40:00Z',
            'Signature' => $generator->generateSignature(
                clientId: 'different-client',
                requestId: 'notif-002',
                requestTimestamp: '2026-04-08T02:40:00Z',
                requestTarget: '/webhooks/doku',
                secretKey: 'super-secret',
                digest: $generator->generateDigest($body),
            ),
        ];

        $verifier = new HttpWebhookVerifier(
            new DokuConfig($config),
            $generator,
            new StatusNormalizer,
        );

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Unexpected DOKU client id.');

        $verifier->parseAndVerify(
            headers: $headers,
            body: $body,
            requestTarget: '/webhooks/doku',
        );
    }
}
