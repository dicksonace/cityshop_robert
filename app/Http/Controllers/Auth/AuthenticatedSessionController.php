<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if ($user = $request->user()) {
            return redirect()->to($user->defaultRedirectRoute());
        }

        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'defaultLogin' => old('login', ''),
        ]);
    }

    public function createSeller(Request $request): Response|RedirectResponse
    {
        if ($user = $request->user()) {
            return redirect()->to($user->defaultRedirectRoute());
        }

        return Inertia::render('auth/seller-login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'defaultLogin' => old('login', ''),
        ]);
    }

    public function createAdmin(Request $request): Response|RedirectResponse
    {
        if ($user = $request->user()) {
            return redirect()->to($user->defaultRedirectRoute());
        }

        return Inertia::render('auth/admin-login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'defaultLogin' => old('login', ''),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            $portal = $request->input('portal', 'buyer');
            $loginRoute = match ($portal) {
                'seller' => route('seller.login'),
                'admin' => route('admin.login'),
                default => route('login'),
            };

            $firstError = collect($e->errors())->flatten()->first();

            return redirect($loginRoute)
                ->withErrors($e->errors())
                ->with('error', is_string($firstError) ? $firstError : 'Login failed. Please try again.')
                ->withInput($request->only('login'));
        }

        $request->session()->regenerate();

        $user = $request->user();

        if ($user->isSeller()) {
            $profile = $user->sellerProfile;
            if (! $profile || $profile->status->value !== 'approved') {
                return redirect()->intended(route('seller.pending', absolute: false));
            }

            if (! $profile->storeCustomization || ! $profile->storeCustomization->isSetupComplete()) {
                return redirect()->intended(route('seller.store-setup', absolute: false));
            }

            return redirect()->intended(route('seller.dashboard', absolute: false));
        }

        return match (true) {
            $user->isAdmin() => redirect()->intended(route('admin.dashboard', absolute: false)),
            default => redirect()->intended(route('home', absolute: false)),
        };
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirect = $request->query('redirect');
        if (is_string($redirect) && str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            return redirect($redirect);
        }

        return redirect('/');
    }
}
