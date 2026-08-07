<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    /**
     * Look up a buyer by email or mobile and email a 6-digit reset code.
     *
     * @return array{sent: bool, email_hint: string|null}
     */
    public static function sendCode(string $login): array
    {
        $throttleKey = 'password-reset-send:'.strtolower($login);
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'login' => ["Please wait {$seconds} seconds before requesting another code."],
            ]);
        }

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';
        $user = User::query()->where($field, $login)->first();

        // Always look like success so we don't leak whether an account exists.
        if (! $user || ! filled($user->email)) {
            RateLimiter::hit($throttleKey, 60);

            return ['sent' => false, 'email_hint' => null];
        }

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => strtolower($user->email)],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ],
        );

        $user->notify(new PasswordResetCodeNotification($code));
        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit('password-reset-send-user:'.$user->id, 60);

        return [
            'sent' => true,
            'email_hint' => static::maskEmail($user->email),
        ];
    }

    public static function resetWithCode(string $login, string $code, string $password): User
    {
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';
        $user = User::query()->where($field, $login)->first();

        if (! $user || ! filled($user->email)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired reset code.'],
            ]);
        }

        $attemptKey = 'password-reset-attempt:'.$user->id;
        if (RateLimiter::tooManyAttempts($attemptKey, 8)) {
            $seconds = RateLimiter::availableIn($attemptKey);
            throw ValidationException::withMessages([
                'code' => ["Too many attempts. Try again in {$seconds} seconds."],
            ]);
        }

        $row = DB::table('password_reset_tokens')
            ->where('email', strtolower($user->email))
            ->first();

        if (! $row || ! Hash::check($code, (string) $row->token)) {
            RateLimiter::hit($attemptKey, 60 * 15);
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired reset code.'],
            ]);
        }

        if ($row->created_at && \Illuminate\Support\Carbon::parse($row->created_at)->lt(now()->subMinutes(30))) {
            DB::table('password_reset_tokens')->where('email', strtolower($user->email))->delete();
            throw ValidationException::withMessages([
                'code' => ['That reset code has expired. Request a new one.'],
            ]);
        }

        $user->forceFill([
            'password' => $password,
        ])->save();

        DB::table('password_reset_tokens')->where('email', strtolower($user->email))->delete();
        $user->tokens()->delete();
        RateLimiter::clear($attemptKey);

        return $user->fresh();
    }

    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];
        $visible = max(1, (int) floor(strlen($local) / 3));

        return substr($local, 0, $visible).str_repeat('*', max(3, strlen($local) - $visible)).'@'.$domain;
    }
}
