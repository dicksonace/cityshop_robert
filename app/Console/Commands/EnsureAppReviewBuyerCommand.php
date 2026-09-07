<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Console\Command;

class EnsureAppReviewBuyerCommand extends Command
{
    public const EMAIL = 'apple.review@cityunlock.net';

    public const PASSWORD = 'CityUnlock2026!';

    public const MOBILE = '0599999159';

    protected $signature = 'cityshop:ensure-app-review-buyer';

    protected $description = 'Create or refresh the Apple App Store review demo buyer account';

    public function handle(): int
    {
        $user = User::withTrashed()->where('email', self::EMAIL)->first();

        if ($user?->trashed()) {
            $user->restore();
        }

        // Keep a dedicated mobile so login by phone also works for reviewers.
        $mobileOwner = User::withTrashed()
            ->where('mobile', self::MOBILE)
            ->where('email', '!=', self::EMAIL)
            ->first();
        if ($mobileOwner) {
            $this->error('Mobile '.self::MOBILE.' is already used by another account. Update MOBILE constant and redeploy.');

            return self::FAILURE;
        }

        $attrs = [
            'name' => 'Apple Reviewer',
            'first_name' => 'Apple',
            'last_name' => 'Reviewer',
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
            'country' => 'Ghana',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'password' => self::PASSWORD,
            'role' => UserRole::Buyer,
            'blocked_at' => null,
            'block_reason' => null,
        ];

        if ($user) {
            $user->fill($attrs);
            $user->email_verified_at = $user->email_verified_at ?? now();
            $user->save();
            $this->info("Updated App Review buyer #{$user->id}.");
        } else {
            $user = User::query()->create($attrs);
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->info("Created App Review buyer #{$user->id}.");
        }

        Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'available_balance' => 0,
                'pending_balance' => 0,
                'total_earnings' => 0,
                'withdrawn_amount' => 0,
                'rmb_balance' => 0,
            ],
        );

        $this->newLine();
        $this->line('App Store Connect → App Review Information:');
        $this->line('  User name: '.self::EMAIL);
        $this->line('  Password:  '.self::PASSWORD);

        return self::SUCCESS;
    }
}
