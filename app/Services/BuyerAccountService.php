<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BuyerAccountService
{
    public function block(User $buyer, string $reason): void
    {
        if (! $buyer->isBuyer()) {
            throw new InvalidArgumentException('Only buyer accounts can be blocked here.');
        }

        if ($buyer->isBlocked()) {
            throw new InvalidArgumentException('This buyer is already blocked.');
        }

        $buyer->update([
            'blocked_at' => now(),
            'block_reason' => $reason,
        ]);

        $buyer->tokens()->delete();
    }

    public function unblock(User $buyer): void
    {
        if (! $buyer->isBuyer()) {
            throw new InvalidArgumentException('Only buyer accounts can be unblocked here.');
        }

        if (! $buyer->isBlocked()) {
            throw new InvalidArgumentException('This buyer is not blocked.');
        }

        $buyer->update([
            'blocked_at' => null,
            'block_reason' => null,
        ]);
    }

    public function delete(User $buyer, ?string $reason = null): void
    {
        if (! $buyer->isBuyer()) {
            throw new InvalidArgumentException('Only buyer accounts can be deleted here.');
        }

        if ($buyer->isAdmin()) {
            throw new InvalidArgumentException('Administrator accounts cannot be deleted.');
        }

        DB::transaction(function () use ($buyer, $reason) {
            $buyer->tokens()->delete();

            if ($reason) {
                $buyer->forceFill(['block_reason' => $reason]);
            }

            $buyer->releaseLoginIdentifiers();
            $buyer->save();
            $buyer->delete();
        });
    }
}
