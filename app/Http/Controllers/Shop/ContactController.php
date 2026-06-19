<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('shop/contact', [
            'contact' => config('marketplace.contact'),
            'subjects' => [
                'general' => 'General Inquiry',
                'order' => 'Order Issue',
                'payment' => 'Payment Problem',
                'seller' => 'Seller Support',
                'technical' => 'Technical Issue',
                'other' => 'Other',
            ],
            'defaults' => [
                'name' => $user?->name ?? '',
                'email' => $user?->email ?? '',
                'phone' => $user?->mobile ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $subjects = array_keys([
            'general' => true,
            'order' => true,
            'payment' => true,
            'seller' => true,
            'technical' => true,
            'other' => true,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', 'string', 'in:'.implode(',', $subjects)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        ContactMessage::create([
            ...$validated,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Thank you! Your message has been sent. We will respond within 24–48 hours.');
    }
}
