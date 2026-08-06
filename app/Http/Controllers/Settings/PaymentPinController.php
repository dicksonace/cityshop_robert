<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\PaymentPinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentPinController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/payment-pin', [
            'hasPaymentPin' => PaymentPinService::hasPin($request->user()),
            'status' => $request->session()->get('status'),
            'emailHint' => $request->session()->get('email_hint'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (PaymentPinService::hasPin($user)) {
            return back()->withErrors(['pin' => 'Payment PIN already set. Change or reset it instead.']);
        }

        $data = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4}$/', 'confirmed'],
        ]);

        PaymentPinService::set($user, $data['pin']);

        return back()->with('status', 'Payment PIN set.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pin' => ['required', 'string', 'regex:/^\d{4}$/', 'confirmed'],
        ]);

        PaymentPinService::change($request->user(), $data['current_pin'], $data['pin']);

        return back()->with('status', 'Payment PIN updated.');
    }

    public function forgot(Request $request): RedirectResponse
    {
        if (! PaymentPinService::hasPin($request->user())) {
            return back()->withErrors(['email' => 'No payment PIN is set yet.']);
        }

        PaymentPinService::sendResetCode($request->user());

        $email = $request->user()->email;
        $parts = explode('@', $email, 2);
        $hint = count($parts) === 2
            ? substr($parts[0], 0, max(1, (int) floor(strlen($parts[0]) / 3))).'***@'.$parts[1]
            : '***';

        return back()->with([
            'status' => 'A reset code was sent to your email.',
            'email_hint' => $hint,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'pin' => ['required', 'string', 'regex:/^\d{4}$/', 'confirmed'],
        ]);

        PaymentPinService::resetWithCode($request->user(), $data['code'], $data['pin']);

        return back()->with('status', 'Payment PIN reset successfully.');
    }
}
