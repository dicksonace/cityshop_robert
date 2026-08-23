<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Countries;
use App\Support\GhanaMobile;
use App\Support\UserRegistrationGuard;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class BuyerRegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/buyer-register', [
            'countries' => Countries::names(),
            'defaultCountry' => Countries::default(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => [
                'required',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $message = UserRegistrationGuard::mobileTakenMessage((string) $value);
                    if ($message) {
                        $fail($message);
                    }
                },
            ],
            'country' => ['required', 'string', 'max:80', Rule::in(Countries::names())],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $message = UserRegistrationGuard::emailTakenMessage($value);
                    if ($message) {
                        $fail($message);
                    }
                },
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        UserRegistrationGuard::releaseTrashedIdentifiers($validated['email'], $validated['mobile']);

        $user = User::create([
            'name' => $validated['name'],
            'mobile' => $validated['mobile'],
            'country' => $validated['country'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::Buyer,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()
            ->route('home')
            ->with('success', 'Welcome to CityShop! Your account was created successfully. Start shopping anytime.');
    }
}
