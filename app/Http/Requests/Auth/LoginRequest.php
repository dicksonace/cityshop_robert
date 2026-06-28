<?php

namespace App\Http\Requests\Auth;

use App\Enums\SellerStatus;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'portal' => ['sometimes', 'string', 'in:buyer,seller,admin'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = $this->string('login');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';

        $user = User::where($field, $login)->first();

        if (! $user || ! Auth::getProvider()->validateCredentials($user, ['password' => $this->password])) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        $portal = $this->input('portal', 'buyer');

        if ($portal === 'seller' && $user->isSeller()) {
            $profile = $user->sellerProfile;

            if (! $profile || $profile->status !== SellerStatus::Approved) {
                RateLimiter::hit($this->throttleKey());

                $message = match ($profile?->status) {
                    SellerStatus::Pending => 'Your seller application is still under review. You can sign in after admin approval (usually 24–48 hours).',
                    SellerStatus::Suspended => 'Your seller account is suspended. Please contact support.',
                    default => 'Your seller account is not active yet. Please contact support.',
                };

                throw ValidationException::withMessages([
                    'login' => $message,
                ]);
            }
        }

        if ($portal === 'seller' && ! $user->isSeller()) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => 'This account is not registered as a seller. Use shopper login or contact support.',
            ]);
        }

        if ($portal === 'admin' && ! $user->isAdmin()) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => 'Administrator access only. Please use the correct login portal.',
            ]);
        }

        if ($portal === 'buyer' && $user->isSeller()) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => 'This is a seller account. Please use Seller Centre login instead.',
            ]);
        }

        if ($portal === 'buyer' && $user->isAdmin()) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => 'This is an administrator account. Please use the admin login page.',
            ]);
        }

        Auth::login($user, $this->boolean('remember'));
        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
