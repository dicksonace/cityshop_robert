<?php

namespace Tests\Unit;

use App\Services\ProductVideoService;
use Tests\TestCase;

class ProductVideoServiceTest extends TestCase
{
    public function test_detects_hevc_product_video(): void
    {
        $path = storage_path('app/boot-full.mp4');
        if (! is_file($path)) {
            $this->markTestSkipped('Boot Go sample video not present locally.');
        }

        $this->assertTrue(ProductVideoService::needsWebCompatTranscode($path));
    }

    public function test_plain_bytes_without_hevc_do_not_need_transcode(): void
    {
        $path = storage_path('app/testing-avc-marker.bin');
        file_put_contents($path, 'ftypisom'.str_repeat('x', 200).'avc1'.str_repeat('y', 200));
        $this->assertFalse(ProductVideoService::needsWebCompatTranscode($path));
        @unlink($path);
    }

    public function test_hvc1_marker_needs_transcode(): void
    {
        $path = storage_path('app/testing-hevc-marker.bin');
        file_put_contents($path, 'ftypisom'.str_repeat('x', 200).'hvc1'.str_repeat('y', 200));
        $this->assertTrue(ProductVideoService::needsWebCompatTranscode($path));
        @unlink($path);
    }
}
