<?php

namespace Tests\Unit;

use App\Support\UploadLimits;
use Tests\TestCase;

class UploadLimitsTest extends TestCase
{
    public function test_parse_ini_size_handles_megabytes(): void
    {
        $this->assertSame(2 * 1024 * 1024, UploadLimits::parseIniSize('2M'));
        $this->assertSame(15 * 1024 * 1024, UploadLimits::parseIniSize('15m'));
    }

    public function test_effective_max_bytes_never_exceeds_app_limit(): void
    {
        $this->assertLessThanOrEqual(
            UploadLimits::appMaxBytes(),
            UploadLimits::effectiveMaxBytes(),
        );
    }

    public function test_inertia_payload_contains_labels_and_message(): void
    {
        $payload = UploadLimits::inertiaPayload();

        $this->assertArrayHasKey('max_bytes', $payload);
        $this->assertArrayHasKey('max_label', $payload);
        $this->assertArrayHasKey('message', $payload);
        $this->assertStringContainsString('Mo', $payload['max_label']);
    }
}
