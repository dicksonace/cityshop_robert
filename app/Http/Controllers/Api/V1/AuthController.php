<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\PasswordResetService;
use App\Support\Countries;
use App\Support\GhanaMobile;
use App\Support\UserRegistrationGuard;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
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
            'country' => ['nullable', 'string', 'max:80', Rule::in(Countries::names())],
            'email' => [
                'nullable',
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
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $email = filled($validated['email'] ?? null) ? $validated['email'] : null;

        $user = User::create([
            'name' => $validated['name'],
            'mobile' => $validated['mobile'],
            'country' => $validated['country'] ?? Countries::default(),
            'email' => $email,
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
            'portal' => ['sometimes', 'string', 'in:buyer,seller,admin'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($validated['login']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'login' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $login = trim($validated['login']);
        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? User::query()->where('email', $login)->first()
            : GhanaMobile::findUserByMobile($login);

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        if ($user->isBlocked()) {
            RateLimiter::hit($throttleKey);
            throw ValidationException::withMessages([
                'login' => 'Your account has been restricted for security reasons. Contact CityShop support if you need help.',
            ]);
        }

        $portal = $validated['portal'] ?? 'buyer';

        if ($portal === 'admin') {
            if (! $user->isAdmin()) {
                RateLimiter::hit($throttleKey);
                throw ValidationException::withMessages([
                    'login' => 'This account is not an administrator.',
                ]);
            }
        }

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

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'via' => ['nullable', 'in:email,sms'],
        ]);

        $login = trim($validated['login']);
        $via = filled($validated['via'] ?? null)
            ? \App\Support\ResetChannel::parse($validated['via'])
            : (filter_var($login, FILTER_VALIDATE_EMAIL)
                ? \App\Support\ResetChannel::EMAIL
                : \App\Support\ResetChannel::SMS);

        $result = PasswordResetService::sendCode($login, $via);

        $destination = $via === 'sms' ? 'phone' : 'email';

        return response()->json([
            'message' => $result['sent']
                ? "A reset code was sent to your {$destination}."
                : "If that account exists, a reset code was sent to the {$destination} on file.",
            'via' => $result['via'],
            'hint' => $result['hint'],
            'email_hint' => $result['hint'],
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        PasswordResetService::resetWithCode(
            trim($validated['login']),
            $validated['code'],
            $validated['password'],
        );

        return response()->json([
            'message' => 'Password updated. You can log in with your new password.',
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
