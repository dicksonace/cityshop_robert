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
            self::RmbVerification => 'RMB verification',
            self::RmbReceived => 'RMB received',
            self::PayoutProcessing => 'Processing payout',
            self::Paid => 'Payout sent',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    /** Buyer-facing label (rmb-wallet style). */
    public function buyerLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Processing',
            self::RmbVerification, self::RmbReceived => 'Verifying payment',
            self::PayoutProcessing => 'Processing payout',
            self::Paid => 'Payout sent',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    public function isProcessingForBuyer(): bool
    {
        return in_array($this, [
            self::Submitted,
            self::RmbVerification,
            self::RmbReceived,
            self::PayoutProcessing,
            self::Paid,
        ], true);
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
            self::PayoutProcessing,
            self::Paid,
            self::Completed,
        ];
    }
}
