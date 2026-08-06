<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserBlock;

class UserBlockService
{
    public static function block(User $blocker, User $blocked): UserBlock
    {
        if ($blocker->id === $blocked->id) {
            throw new \RuntimeException('You cannot block yourself.');
        }

        return UserBlock::query()->firstOrCreate([
            'blocker_id' => $blocker->id,
            'blocked_id' => $blocked->id,
        ]);
    }

    public static function unblock(User $blocker, User $blocked): void
    {
        UserBlock::query()
            ->where('blocker_id', $blocker->id)
            ->where('blocked_id', $blocked->id)
            ->delete();
    }

    public static function isBlockedEitherWay(User $a, User $b): bool
    {
        return UserBlock::isBlockedEitherWay($a->id, $b->id);
    }

    public static function iBlocked(User $me, User $other): bool
    {
        return UserBlock::query()
            ->where('blocker_id', $me->id)
            ->where('blocked_id', $other->id)
            ->exists();
    }
}
