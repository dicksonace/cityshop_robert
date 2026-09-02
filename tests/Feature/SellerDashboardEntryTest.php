<?php

namespace Tests\Feature;

use Tests\TestCase;

class SellerDashboardEntryTest extends TestCase
{
    public function test_seller_root_redirects_guests_to_seller_login(): void
    {
        $this->get('/seller')
            ->assertRedirect(route('seller.login'));
    }

    public function test_seller_dashboard_redirects_guests_to_seller_login(): void
    {
        $this->get('/seller/dashboard')
            ->assertRedirect(route('seller.login'));
    }

    public function test_admin_root_redirects_guests_to_admin_login(): void
    {
        $this->get('/admin24')
            ->assertRedirect(route('admin.login'));
    }
}
