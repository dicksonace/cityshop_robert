<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;

class WalletService
{
    public static function ensure(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'available_balance' => 0,
                'pending_balance' => 0,
                'total_earnings' => 0,
                'withdrawn_amount' => 0,
            ]
        );
    }
}
