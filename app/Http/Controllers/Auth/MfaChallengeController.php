<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MfaChallengeController extends Controller
{
    public function __construct(
        private MfaService $mfa,
        private AuthenticatedSessionController $sessions,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('login');
        }

        $user = User::query()->find($pending['user_id']);
        if (! $user || ! $this->mfa->requires($user)) {
            $request->session()->forget('mfa_login');

            return redirect()->route('login');
        }

        return Inertia::render('auth/mfa', [
            'methods' => $this->mfa->methods($user),
            'emailHint' => $this->mfa->destinationHint($user),
            'codeChannel' => $this->mfa->codeChannel(),
            'portal' => $pending['portal'],
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('login')->withErrors(['code' => 'This sign-in check expired. Log in again.']);
        }

        $validated = $request->validate([
            'method' => ['required', 'in:email,totp'],
            'code' => ['required', 'string', 'max:12'],
        ]);

        $user = User::query()->find($pending['user_id']);
        if (! $user) {
            $request->session()->forget('mfa_login');

            return redirect()->route('login');
        }

        $this->mfa->verifyLogin($user, $validated['method'], $validated['code']);

        $request->session()->forget('mfa_login');
        Auth::login($user, (bool) $pending['remember']);
        $request->session()->regenerate();

        return $this->sessions->redirectFor($user);
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('login');
        }

        $user = User::query()->find($pending['user_id']);
        if (! $user) {
            return redirect()->route('login');
        }

        try {
            $this->mfa->sendEmailCode($user);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', $this->mfa->sentMessage($user));
    }

    /**
     * @return array{user_id: int, remember: bool, portal: string, expires: int}|null
     */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get('mfa_login');
        if (! is_array($pending) || (int) ($pending['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget('mfa_login');

            return null;
        }

        return [
            'user_id' => (int) $pending['user_id'],
            'remember' => (bool) ($pending['remember'] ?? false),
            'portal' => (string) ($pending['portal'] ?? 'buyer'),
            'expires' => (int) $pending['expires'],
        ];
    }
}
