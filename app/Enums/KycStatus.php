<?php

namespace App\Enums;

enum KycStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case NeedsImprovement = 'needs_improvement';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for review',
            self::Approved => 'Verified',
            self::Rejected => 'Not approved',
            self::NeedsImprovement => 'Needs improvement',
        };
    }
}
