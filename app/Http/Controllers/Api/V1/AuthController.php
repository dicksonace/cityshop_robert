<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:20', 'unique:users,mobile'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Buyer,
        ]);

        event(new Registered($user));

        $token = $user->createToken($validated['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'portal' => ['sometimes', 'string', 'in:buyer,seller'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($validated['login']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'login' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';
        $user = User::where($field, $validated['login'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        $portal = $validated['portal'] ?? 'buyer';

        if ($portal === 'seller') {
            if (! $user->isSeller()) {
                RateLimiter::hit($throttleKey);
                throw ValidationException::withMessages([
                    'login' => 'This account is not registered as a seller.',
                ]);
            }

            $profile = $user->sellerProfile;
            if (! $profile || $profile->status !== SellerStatus::Approved) {
                RateLimiter::hit($throttleKey);
                throw ValidationException::withMessages([
                    'login' => 'Your seller account is not active yet.',
                ]);
            }
        }

        if ($portal === 'buyer' && ($user->isSeller() || $user->isAdmin())) {
            RateLimiter::hit($throttleKey);
            throw ValidationException::withMessages([
                'login' => $user->isSeller()
                    ? 'This is a seller account. Use portal=seller.'
                    : 'Administrator accounts cannot use the mobile buyer API.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $token = $user->createToken($validated['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
