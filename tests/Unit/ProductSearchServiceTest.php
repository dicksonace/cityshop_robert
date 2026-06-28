<?php

namespace Tests\Unit;

use App\Services\ProductSearchService;
use PHPUnit\Framework\TestCase;

class ProductSearchServiceTest extends TestCase
{
    public function test_it_tokenizes_and_removes_stop_words(): void
    {
        $service = new ProductSearchService;

        $parsed = $service->parseQuery('Samsung Galaxy phone in Ghana');

        $this->assertContains('samsung', $parsed['tokens']);
        $this->assertContains('galaxy', $parsed['tokens']);
        $this->assertContains('phone', $parsed['tokens']);
        $this->assertNotContains('in', $parsed['tokens']);
        $this->assertNotContains('ghana', $parsed['tokens']);
    }

    public function test_it_keeps_short_queries_as_single_token(): void
    {
        $service = new ProductSearchService;

        $parsed = $service->parseQuery('tv');

        $this->assertSame(['tv'], $parsed['tokens']);
    }
}
