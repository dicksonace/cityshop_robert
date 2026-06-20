<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_visit_the_home_page()
    {
        $this->get('/')->assertOk();
    }

    public function test_authenticated_buyers_can_visit_the_home_page()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertOk();
    }
}
