<?php

namespace DokuLaravel\Tests\Unit;

use DokuLaravel\Support\StatusNormalizer;
use PHPUnit\Framework\TestCase;

class StatusNormalizerTest extends TestCase
{
    public function test_it_maps_known_provider_statuses(): void
    {
        $normalizer = new StatusNormalizer;

        $this->assertSame('paid', $normalizer->normalize('SUCCESS'));
        $this->assertSame('pending', $normalizer->normalize('PENDING'));
        $this->assertSame('failed', $normalizer->normalize('FAILED'));
        $this->assertSame('expired', $normalizer->normalize('EXPIRED'));
        $this->assertSame('cancelled', $normalizer->normalize('CANCELLED'));
        $this->assertSame('refunded', $normalizer->normalize('REFUNDED'));
        $this->assertSame('unknown', $normalizer->normalize('SOMETHING_NEW'));
    }
}
