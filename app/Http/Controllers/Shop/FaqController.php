<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('shop/faq', [
            'faq' => config('marketplace.faq'),
            'contact' => config('marketplace.contact'),
        ]);
    }
}
