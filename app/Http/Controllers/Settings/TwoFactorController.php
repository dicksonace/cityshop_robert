<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\MfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function __construct(private MfaService $mfa) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('settings/security', [
            'mfa' => $this->mfa->status($request->user()),
            'setup' => $request->session()->get('mfa_setup'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function sendEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);
        $user = $request->user();
        $this->mfa->assertPassword($user, $validated['password']);
        $this->mfa->sendEmailCode($user, 'setup');

        return back()->with('status', $this->mfa->sentMessage($user));
    }

    public function confirmEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ]);
        $user = $request->user();
        $this->mfa->verifyEmailCode($user, $validated['code'], 'setup');
        $this->mfa->enableEmail($user);

        return back()->with('status', match ($this->mfa->codeChannel()) {
            'email' => 'Email sign-in codes are on.',
            'both' => 'SMS and email sign-in codes are on.',
            default => 'SMS sign-in codes are on.',
        });
    }

    public function disableEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);
        $user = $request->user();
        $this->mfa->assertPassword($user, $validated['password']);
        $this->mfa->disableEmail($user);

        return back()->with('status', 'Email sign-in codes are off.');
    }

    public function startTotp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);
        $user = $request->user();
        $this->mfa->assertPassword($user, $validated['password']);

        return back()->with('mfa_setup', $this->mfa->startTotp($user));
    }

    public function confirmTotp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ]);
        $this->mfa->confirmTotp($request->user(), $validated['code']);

        return back()->with('status', 'Authenticator app is on.');
    }

    public function disableTotp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);
        $user = $request->user();
        $this->mfa->assertPassword($user, $validated['password']);
        $this->mfa->disableTotp($user);

        return back()->with('status', 'Authenticator app is off.');
    }
}
