<?php

namespace App\Services;

use App\Enums\SellerStatus;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Notifications\SellerSuspendedNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SellerAccountService
{
    public function block(SellerProfile $seller, string $reason): void
    {
        if ($seller->status === SellerStatus::Suspended) {
            throw new InvalidArgumentException('This seller is already blocked.');
        }

        if ($seller->status !== SellerStatus::Approved) {
            throw new InvalidArgumentException('Only approved sellers can be blocked.');
        }

        $seller->update([
            'status' => SellerStatus::Suspended,
            'rejection_reason' => $reason,
        ]);

        $seller->user->notify(new SellerSuspendedNotification($reason));
    }

    public function unblock(SellerProfile $seller, int $approvedBy): void
    {
        if ($seller->status !== SellerStatus::Suspended) {
            throw new InvalidArgumentException('This seller is not blocked.');
        }

        $seller->update([
            'status' => SellerStatus::Approved,
            'rejection_reason' => null,
            'approved_at' => now(),
            'approved_by' => $approvedBy,
        ]);
    }

    public function delete(SellerProfile $seller, ?string $reason = null): void
    {
        $user = $seller->user;

        if ($user->isAdmin()) {
            throw new InvalidArgumentException('Administrator accounts cannot be deleted.');
        }

        DB::transaction(function () use ($seller, $user, $reason) {
            Product::where('seller_id', $user->id)->each(fn (Product $product) => $product->delete());

            $seller->update([
                'status' => SellerStatus::Suspended,
                'rejection_reason' => $reason ?? 'Account removed by admin.',
            ]);

            $user->delete();
        });
    }
}
