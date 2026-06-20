<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register/buyer');

        $response->assertStatus(200);
    }

    public function test_new_buyers_can_register()
    {
        $response = $this->post('/register/buyer', [
            'name' => 'Test User',
            'mobile' => '0241234567',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));
    }
}
