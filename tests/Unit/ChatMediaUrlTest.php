<?php

namespace Tests\Unit;

use App\Services\ChatService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatMediaUrlTest extends TestCase
{
    public function test_public_media_url_prefers_storage_path_over_stale_absolute_url(): void
    {
        Storage::fake('public');

        $url = ChatService::publicMediaUrl(
            'https://old-host.example/storage/chat/1/clip.mp4',
            'chat/1/clip.mp4',
        );

        $this->assertIsString($url);
        $this->assertStringEndsWith('/storage/chat/1/clip.mp4', $url);
        $this->assertStringNotContainsString('old-host.example', $url);
    }

    public function test_public_media_url_rewrites_storage_urls_to_current_host(): void
    {
        Storage::fake('public');

        $url = ChatService::publicMediaUrl('https://127.0.0.1:8000/storage/chat/9/photo.jpg');

        $this->assertIsString($url);
        $this->assertStringEndsWith('/storage/chat/9/photo.jpg', $url);
        $this->assertStringNotContainsString('127.0.0.1', $url);
    }

    public function test_public_media_url_returns_null_when_empty(): void
    {
        $this->assertNull(ChatService::publicMediaUrl(null, null));
        $this->assertNull(ChatService::publicMediaUrl(''));
    }
}
