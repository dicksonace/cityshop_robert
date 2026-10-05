<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Support\Totp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class MfaService
{
    public function requires(User $user): bool
    {
        return $this->methods($user) !== [];
    }

    /**
     * @return list<string>
     */
    public function methods(User $user): array
    {
        $methods = [];
        if ($user->email_two_factor_enabled && $this->canDeliverCode($user)) {
            $methods[] = 'email';
        }
        if ($user->totp_confirmed_at && filled($user->totp_secret)) {
            $methods[] = 'totp';
        }

        return $methods;
    }

    public function emailHint(User $user): ?string
    {
        $email = (string) $user->email;
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        [$name, $domain] = explode('@', $email, 2);
        $visible = substr($name, 0, 1);

        return $visible.'***@'.$domain;
    }

    public function codeChannel(): string
    {
        return PlatformSettings::normalizeCodeChannel((string) (PlatformSettings::smsSettings()['code_channel'] ?? 'sms'));
    }

    public function mobileHint(User $user): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $user->mobile) ?? '';
        if (strlen($digits) < 6) {
            return null;
        }

        return substr($digits, 0, 3).'****'.substr($digits, -2);
    }

    public function destinationHint(User $user): string
    {
        return match ($this->codeChannel()) {
            'email' => $this->emailHint($user) ?? 'your email',
            'both' => trim(($this->mobileHint($user) ?? 'your phone').' and '.($this->emailHint($user) ?? 'your email')),
            default => $this->mobileHint($user) ?? 'your phone',
        };
    }

    public function sentMessage(User $user): string
    {
        return match ($this->codeChannel()) {
            'email' => 'A code was sent to '.$this->destinationHint($user).'.',
            'both' => 'A code was sent by SMS and email.',
            default => 'A code was sent by SMS to '.$this->destinationHint($user).'.',
        };
    }

    public function status(User $user): array
    {
        $channel = $this->codeChannel();

        return [
            'email' => $user->email,
            'email_hint' => $this->emailHint($user),
            'has_email' => filled($user->email),
            'mobile' => $user->mobile,
            'mobile_hint' => $this->mobileHint($user),
            'has_mobile' => filled($user->mobile),
            'code_channel' => $channel,
            'email_enabled' => (bool) $user->email_two_factor_enabled && $this->canDeliverCode($user),
            'totp_enabled' => $user->totp_confirmed_at !== null && filled($user->totp_secret),
        ];
    }

    public function assertPassword(User $user, string $password): void
    {
        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Password is incorrect.',
            ]);
        }
    }

    public function sendEmailCode(User $user, string $purpose = 'login'): void
    {
        $channel = $this->codeChannel();
        $sendSms = in_array($channel, ['sms', 'both'], true);
        $sendMail = in_array($channel, ['email', 'both'], true);

        if ($sendSms && ! filled($user->mobile) && ! ($sendMail && filled($user->email))) {
            throw ValidationException::withMessages([
                'mobile' => 'Add a phone number before using SMS sign-in codes.',
            ]);
        }
        if ($sendMail && ! filled($user->email) && ! ($sendSms && filled($user->mobile))) {
            throw ValidationException::withMessages([
                'email' => 'Add an email address before using email sign-in codes.',
            ]);
        }

        $key = 'mfa-email-send:'.$user->id.':'.$purpose;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'code' => "A code was just sent. Wait {$seconds} seconds before sending another.",
            ]);
        }

        $code = (string) random_int(100000, 999999);
        Cache::put($this->emailCacheKey($user, $purpose), Hash::make($code), now()->addMinutes(10));

        $sent = false;
        if ($sendSms && filled($user->mobile)) {
            $sent = app(SmsService::class)->send(
                (string) $user->mobile,
                'Your CityUnlock code is '.$code.'. It expires in 10 minutes.',
            ) || $sent;
        }
        if ($sendMail && filled($user->email)) {
            try {
                $user->notify(new TwoFactorCodeNotification($code));
                $sent = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (! $sent) {
            Cache::forget($this->emailCacheKey($user, $purpose));
            throw ValidationException::withMessages([
                'code' => $sendSms
                    ? 'The SMS could not be sent. Try again in a moment.'
                    : 'The email could not be sent. Try again in a moment.',
            ]);
        }

        RateLimiter::hit($key, 60);
    }

    public function verifyEmailCode(User $user, string $code, string $purpose = 'login', bool $consume = true): void
    {
        $cached = Cache::get($this->emailCacheKey($user, $purpose));
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! is_string($cached) || ! preg_match('/^\d{6}$/', $code) || ! Hash::check($code, $cached)) {
            throw ValidationException::withMessages([
                'code' => 'That code is incorrect or expired.',
            ]);
        }

        if ($consume) {
            Cache::forget($this->emailCacheKey($user, $purpose));
        }
    }

    public function enableEmail(User $user): void
    {
        $user->forceFill(['email_two_factor_enabled' => true])->save();
    }

    public function disableEmail(User $user): void
    {
        $user->forceFill(['email_two_factor_enabled' => false])->save();
        Cache::forget($this->emailCacheKey($user, 'login'));
        Cache::forget($this->emailCacheKey($user, 'setup'));
    }

    /**
     * @return array{secret: string, otpauth_url: string}
     */
    public function startTotp(User $user): array
    {
        $secret = Totp::generateSecret();
        Cache::put($this->totpSetupKey($user), $secret, now()->addMinutes(15));
        $account = filled($user->email) ? (string) $user->email : (string) $user->mobile;

        return [
            'secret' => $secret,
            'otpauth_url' => Totp::provisioningUri($secret, $account !== '' ? $account : 'user-'.$user->id),
        ];
    }

    public function confirmTotp(User $user, string $code): void
    {
        $secret = Cache::get($this->totpSetupKey($user));
        if (! is_string($secret) || $secret === '') {
            throw ValidationException::withMessages([
                'code' => 'Start authenticator setup again. The QR code expired.',
            ]);
        }

        if (! Totp::verify($secret, $code)) {
            throw ValidationException::withMessages([
                'code' => 'That authenticator code is incorrect.',
            ]);
        }

        $user->forceFill([
            'totp_secret' => $secret,
            'totp_confirmed_at' => now(),
        ])->save();
        Cache::forget($this->totpSetupKey($user));
    }

    public function disableTotp(User $user): void
    {
        $user->forceFill([
            'totp_secret' => null,
            'totp_confirmed_at' => null,
        ])->save();
        Cache::forget($this->totpSetupKey($user));
    }

    public function verifyTotp(User $user, string $code): void
    {
        $secret = (string) $user->totp_secret;
        if ($secret === '' || ! Totp::verify($secret, $code)) {
            throw ValidationException::withMessages([
                'code' => 'That authenticator code is incorrect.',
            ]);
        }
    }

    public function verifyLogin(User $user, string $method, string $code): void
    {
        $attemptKey = 'mfa-verify:'.$user->id;
        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            $seconds = RateLimiter::availableIn($attemptKey);
            throw ValidationException::withMessages([
                'code' => "Too many wrong codes. Try again in {$seconds} seconds.",
            ]);
        }

        try {
            if ($method === 'email') {
                if (! $user->email_two_factor_enabled) {
                    throw ValidationException::withMessages(['method' => 'Email codes are not turned on.']);
                }
                $this->verifyEmailCode($user, $code, 'login');
            } elseif ($method === 'totp') {
                if (! $user->totp_confirmed_at) {
                    throw ValidationException::withMessages(['method' => 'Authenticator is not turned on.']);
                }
                $this->verifyTotp($user, $code);
            } else {
                throw ValidationException::withMessages(['method' => 'Choose email or authenticator.']);
            }
        } catch (ValidationException $e) {
            RateLimiter::hit($attemptKey, 300);
            throw $e;
        }

        RateLimiter::clear($attemptKey);
    }

    public function issueApiChallenge(User $user, string $portal, ?string $device): string
    {
        return Crypt::encryptString(json_encode([
            'uid' => $user->id,
            'portal' => $portal,
            'device' => $device,
            'exp' => now()->addMinutes(10)->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{uid: int, portal: string, device: ?string}
     */
    public function parseApiChallenge(string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'mfa_token' => 'This sign-in check expired. Log in again.',
            ]);
        }

        if (! is_array($payload) || (int) ($payload['exp'] ?? 0) < now()->timestamp || ! isset($payload['uid'])) {
            throw ValidationException::withMessages([
                'mfa_token' => 'This sign-in check expired. Log in again.',
            ]);
        }

        return [
            'uid' => (int) $payload['uid'],
            'portal' => (string) ($payload['portal'] ?? 'buyer'),
            'device' => isset($payload['device']) ? (string) $payload['device'] : null,
        ];
    }

    private function canDeliverCode(User $user): bool
    {
        return match ($this->codeChannel()) {
            'email' => filled($user->email),
            'both' => filled($user->mobile) || filled($user->email),
            default => filled($user->mobile),
        };
    }

    private function emailCacheKey(User $user, string $purpose): string
    {
        return 'mfa-email:'.$purpose.':'.$user->id;
    }

    private function totpSetupKey(User $user): string
    {
        return 'mfa-totp-setup:'.$user->id;
    }
}
