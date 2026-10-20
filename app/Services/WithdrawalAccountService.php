<?php

namespace App\Services;

use App\Models\SellerPayoutMethod;
use App\Models\User;

/**
 * MoMo and bank accounts a buyer or seller can reuse on the next withdrawal.
 */
class WithdrawalAccountService
{
    /**
     * @return list<array{id: int, type: string, network: string, network_label: string, account_number: string, account_name: string, is_default: bool}>
     */
    public static function listFor(User $user): array
    {
        return SellerPayoutMethod::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get(['id', 'type', 'network', 'account_number', 'account_name', 'is_default'])
            ->map(fn (SellerPayoutMethod $method) => [
                'id' => $method->id,
                'type' => $method->type,
                'network' => $method->network,
                'network_label' => $method->networkLabel(),
                'account_number' => $method->account_number,
                'account_name' => $method->account_name,
                'is_default' => (bool) $method->is_default,
            ])
            ->all();
    }

    /**
     * Store the account, or update the name when the same number was saved before.
     */
    public static function remember(User $user, string $type, string $network, string $number, string $name): SellerPayoutMethod
    {
        $type = $type === 'bank' ? 'bank' : 'momo';
        $number = trim($number);
        $name = trim($name);

        $existing = SellerPayoutMethod::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('account_number', $number)
            ->first();

        if ($existing) {
            $existing->update([
                'network' => $network,
                'account_name' => $name,
            ]);

            return $existing->fresh();
        }

        $isFirst = ! SellerPayoutMethod::query()->where('user_id', $user->id)->exists();

        return SellerPayoutMethod::create([
            'user_id' => $user->id,
            'type' => $type,
            'network' => $network,
            'account_number' => $number,
            'account_name' => $name,
            'is_default' => $isFirst,
        ]);
    }
}
