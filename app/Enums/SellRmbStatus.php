<?php

namespace App\Enums;

enum SellRmbStatus: string
{
    case Submitted = 'submitted';
    case RmbVerification = 'rmb_verification';
    case RmbReceived = 'rmb_received';
    case PayoutProcessing = 'payout_processing';
    case Paid = 'paid';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::RmbVerification => 'RMB Verification',
            self::RmbReceived => 'RMB Received',
            self::PayoutProcessing => 'Payout Processing',
            self::Paid => 'Paid',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::Submitted,
            self::RmbVerification,
            self::RmbReceived,
            self::PayoutProcessing,
            self::Paid,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Rejected,
            self::Cancelled,
            self::Failed,
        ], true);
    }

    public function isImmutable(): bool
    {
        return $this === self::Completed;
    }

    /** @return list<self> */
    public static function timeline(): array
    {
        return [
            self::Submitted,
            self::RmbVerification,
            self::RmbReceived,
            self::PayoutProcessing,
            self::Paid,
            self::Completed,
        ];
    }
}
