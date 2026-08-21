<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use App\Support\GhanaMobile;
use App\Support\NotificationPrivacy;
use App\Support\ResetChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    /**
     * Look up a buyer by email or mobile and send a 6-digit reset code.
     *
     * @return array{sent: bool, via: string, hint: string|null, email_hint: string|null}
     */
    public static function sendCode(string $login, string $via = ResetChannel::EMAIL): array
    {
        $via = ResetChannel::parse($via);
        $login = trim($login);
        $throttleKey = 'password-reset-send:'.strtolower($login).':'.$via;
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'login' => ["Please wait {$seconds} seconds before requesting another code."],
            ]);
        }

        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? User::query()->where('email', $login)->first()
            : GhanaMobile::findUserByMobile($login);

        // Always look like success so we don't leak whether an account exists.
        if (! $user || ! static::canSend($user, $via)) {
            RateLimiter::hit($throttleKey, 60);

            return [
                'sent' => false,
                'via' => $via,
                'hint' => null,
                'email_hint' => null,
            ];
        }

        $code = (string) random_int(100000, 999999);
        $tokenKey = static::tokenKey($user);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $tokenKey],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ],
        );

        $user->notify(new PasswordResetCodeNotification($code, $via));
        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit('password-reset-send-user:'.$user->id, 60);

        $hint = ResetChannel::hint($via, $user->email, $user->mobile);

        return [
            'sent' => true,
            'via' => $via,
            'hint' => $hint,
            'email_hint' => $via === ResetChannel::EMAIL ? $hint : null,
        ];
    }

    public static function resetWithCode(string $login, string $code, string $password): User
    {
        $login = trim($login);
        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? User::query()->where('email', $login)->first()
            : GhanaMobile::findUserByMobile($login);

        if (! $user) {
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

        $tokenKey = static::tokenKey($user);
        $row = DB::table('password_reset_tokens')
            ->where('email', $tokenKey)
            ->first();

        if (! $row || ! Hash::check($code, (string) $row->token)) {
            RateLimiter::hit($attemptKey, 60 * 15);
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired reset code.'],
            ]);
        }

        if ($row->created_at && \Illuminate\Support\Carbon::parse($row->created_at)->lt(now()->subMinutes(30))) {
            DB::table('password_reset_tokens')->where('email', $tokenKey)->delete();
            throw ValidationException::withMessages([
                'code' => ['That reset code has expired. Request a new one.'],
            ]);
        }

        $user->forceFill([
            'password' => $password,
        ])->save();

        DB::table('password_reset_tokens')->where('email', $tokenKey)->delete();
        $user->tokens()->delete();
        RateLimiter::clear($attemptKey);

        return $user->fresh();
    }

    public static function maskEmail(string $email): string
    {
        return NotificationPrivacy::maskEmail($email);
    }

    private static function canSend(User $user, string $via): bool
    {
        if ($via === ResetChannel::SMS) {
            return filled($user->mobile) && filled(static::tokenKey($user));
        }

        return filled($user->email);
    }

    private static function tokenKey(User $user): string
    {
        if (filled($user->email)) {
            return strtolower((string) $user->email);
        }

        $mobile = preg_replace('/\D+/', '', (string) $user->mobile) ?? '';

        return $mobile !== '' ? 'm:'.$mobile : '';
    }
}
