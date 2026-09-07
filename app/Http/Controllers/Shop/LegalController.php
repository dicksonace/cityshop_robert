<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function privacy(): Response
    {
        return Inertia::render('shop/privacy', [
            'contact' => config('marketplace.contact'),
            'updatedAt' => '7 September 2026',
        ]);
    }

    public function terms(): Response
    {
        return Inertia::render('shop/terms', [
            'contact' => config('marketplace.contact'),
            'updatedAt' => '7 September 2026',
        ]);
    }
}
