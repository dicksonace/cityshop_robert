<?php

namespace Tests\Unit;

use App\Support\GhanaMobile;
use Tests\TestCase;

class GhanaMobileTest extends TestCase
{
    public function test_variants_treat_local_and_233_as_same_number(): void
    {
        $fromLocal = GhanaMobile::variants('0248620718');
        $from233 = GhanaMobile::variants('233248620718');
        $fromPlus = GhanaMobile::variants('+233248620718');

        $this->assertContains('0248620718', $fromLocal);
        $this->assertContains('233248620718', $fromLocal);
        $this->assertContains('0248620718', $from233);
        $this->assertContains('233248620718', $fromPlus);
        $this->assertSame('233248620718', GhanaMobile::to233('0248620718'));
        $this->assertSame('233248620718', GhanaMobile::to233('233248620718'));
    }
}
