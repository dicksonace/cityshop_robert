<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function privacy(): View
    {
        $contact = config('marketplace.contact', []);

        return view('legal.privacy', [
            'title' => 'Privacy Policy',
            'description' => 'How CityUnlock collects, uses, and protects your personal data on website and mobile apps.',
            'canonical' => url('/privacy'),
            'email' => $contact['email'] ?? 'support@cityunlock.net',
            'phone' => $contact['phone'] ?? ($contact['whatsapp'] ?? null),
            'updatedAt' => '7 September 2026',
        ]);
    }

    public function terms(): View
    {
        $contact = config('marketplace.contact', []);

        return view('legal.terms', [
            'title' => 'Terms of Service',
            'description' => 'Terms for using CityUnlock marketplace, wallet, and related services.',
            'canonical' => url('/terms'),
            'email' => $contact['email'] ?? 'support@cityunlock.net',
            'updatedAt' => '7 September 2026',
        ]);
    }
}
