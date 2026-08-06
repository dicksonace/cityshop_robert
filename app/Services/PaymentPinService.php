<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PaymentPinResetCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PaymentPinService
{
    public static function hasPin(User $user): bool
    {
        return filled($user->payment_pin);
    }

    public static function set(User $user, string $pin): void
    {
        static::assertValidPin($pin);

        $user->forceFill([
            'payment_pin' => Hash::make($pin),
        ])->save();
    }

    /**
     * Verify a PIN for a money action. Throws ValidationException on failure.
     */
    public static function assertValidForAction(User $user, ?string $pin): void
    {
        if (! static::hasPin($user)) {
            throw ValidationException::withMessages([
                'payment_pin' => ['Set a 4-digit payment PIN first in Profile → Payment PIN.'],
            ]);
        }

        if (! is_string($pin) || ! preg_match('/^\d{4}$/', $pin)) {
            throw ValidationException::withMessages([
                'payment_pin' => ['Enter your 4-digit payment PIN.'],
            ]);
        }

        $key = 'payment-pin:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'payment_pin' => ["Too many wrong PIN attempts. Try again in {$seconds} seconds."],
            ]);
        }

        if (! Hash::check($pin, (string) $user->payment_pin)) {
            RateLimiter::hit($key, 60 * 15);
            throw ValidationException::withMessages([
                'payment_pin' => ['Incorrect payment PIN.'],
            ]);
        }

        RateLimiter::clear($key);
    }

    public static function change(User $user, string $currentPin, string $newPin): void
    {
        static::assertValidForAction($user, $currentPin);
        static::assertValidPin($newPin);

        if ($currentPin === $newPin) {
            throw ValidationException::withMessages([
                'pin' => ['New PIN must be different from your current PIN.'],
            ]);
        }

        static::set($user, $newPin);
    }

    public static function sendResetCode(User $user): void
    {
        $key = 'payment-pin-reset-send:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => ["Please wait {$seconds} seconds before requesting another code."],
            ]);
        }

        $code = (string) random_int(100000, 999999);

        DB::table('payment_pin_reset_tokens')->updateOrInsert(
            ['email' => strtolower($user->email)],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ],
        );

        $user->notify(new PaymentPinResetCodeNotification($code));
        RateLimiter::hit($key, 60);
    }

    public static function resetWithCode(User $user, string $code, string $newPin): void
    {
        static::assertValidPin($newPin);

        $row = DB::table('payment_pin_reset_tokens')
            ->where('email', strtolower($user->email))
            ->first();

        if (! $row || ! Hash::check($code, (string) $row->token)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired reset code.'],
            ]);
        }

        if ($row->created_at && \Illuminate\Support\Carbon::parse($row->created_at)->lt(now()->subMinutes(30))) {
            DB::table('payment_pin_reset_tokens')->where('email', strtolower($user->email))->delete();
            throw ValidationException::withMessages([
                'code' => ['That reset code has expired. Request a new one.'],
            ]);
        }

        static::set($user, $newPin);
        DB::table('payment_pin_reset_tokens')->where('email', strtolower($user->email))->delete();
        RateLimiter::clear('payment-pin:'.$user->id);
    }

    private static function assertValidPin(string $pin): void
    {
        if (! preg_match('/^\d{4}$/', $pin)) {
            throw ValidationException::withMessages([
                'pin' => ['Payment PIN must be exactly 4 digits.'],
            ]);
        }

        // Block trivial PINs.
        if (preg_match('/^(\d)\1{3}$/', $pin) || in_array($pin, ['1234', '4321', '0000', '1111', '2580'], true)) {
            throw ValidationException::withMessages([
                'pin' => ['Choose a stronger PIN. Avoid easy codes like 1234 or 0000.'],
            ]);
        }
    }
}
