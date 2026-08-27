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

    public function test_avc_marker_does_not_need_transcode_without_ffprobe_hevc(): void
    {
        $path = storage_path('app/testing-avc-marker.bin');
        file_put_contents($path, 'ftypisom'.str_repeat('x', 200).'avc1'.str_repeat('y', 200));
        // Marker path only (ffprobe fails on this fake file).
        $this->assertFalse(ProductVideoService::needsWebCompatTranscodeByMarkers($path));
        @unlink($path);
    }

    public function test_hvc1_marker_needs_transcode(): void
    {
        $path = storage_path('app/testing-hevc-marker.bin');
        file_put_contents($path, 'ftypisom'.str_repeat('x', 200).'hvc1'.str_repeat('y', 200));
        $this->assertTrue(ProductVideoService::needsWebCompatTranscodeByMarkers($path));
        @unlink($path);
    }

    public function test_no_avc_marker_needs_transcode(): void
    {
        $path = storage_path('app/testing-unknown-marker.bin');
        file_put_contents($path, 'ftypisom'.str_repeat('z', 400));
        $this->assertTrue(ProductVideoService::needsWebCompatTranscodeByMarkers($path));
        @unlink($path);
    }

    public function test_chrome_safe_codec_names(): void
    {
        $this->assertTrue(ProductVideoService::isChromeSafeVideoCodec('h264'));
        $this->assertTrue(ProductVideoService::isChromeSafeVideoCodec('AVC1'));
        $this->assertFalse(ProductVideoService::isChromeSafeVideoCodec('hevc'));
        $this->assertFalse(ProductVideoService::isChromeSafeVideoCodec('av1'));
        $this->assertFalse(ProductVideoService::isChromeSafeVideoCodec('mpeg4'));
    }
}
