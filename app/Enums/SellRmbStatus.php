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

    /** Badge text on buyer status screens (rmb-wallet style). */
    public function buyerBadgeLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Pending',
            self::RmbVerification, self::RmbReceived => 'Verifying',
            self::PayoutProcessing, self::Paid => 'Processing',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    public function buyerHeaderTitle(): string
    {
        return match ($this) {
            self::Submitted, self::RmbVerification, self::RmbReceived => 'Awaiting Review',
            self::PayoutProcessing, self::Paid => 'Processing Payout',
            self::Completed => 'Payout Complete!',
            self::Rejected => 'Sell Request Rejected',
            self::Cancelled => 'Sell Request Cancelled',
            self::Failed => 'Sell Request Failed',
        };
    }

    public function buyerHeaderSubtitle(): string
    {
        return match ($this) {
            self::Submitted, self::RmbVerification, self::RmbReceived => 'Your RMB sell is being verified',
            self::PayoutProcessing, self::Paid => 'Admin is sending GHS to your MoMo',
            self::Completed => 'GHS has been sent to your Mobile Money',
            self::Rejected => 'See details below',
            self::Cancelled => 'This sell request was cancelled',
            self::Failed => 'See details below',
        };
    }

    public function buyerHeaderColor(): string
    {
        return match ($this) {
            self::Submitted, self::RmbVerification, self::RmbReceived => '#ef4444',
            self::PayoutProcessing, self::Paid => '#3b82f6',
            self::Completed => '#22c55e',
            self::Rejected, self::Failed => '#dc2626',
            self::Cancelled => '#6b7280',
        };
    }

    /** @return array{header_title: string, header_subtitle: string, header_color: string, badge_label: string, badge_class: string} */
    public function buyerPresentation(): array
    {
        $badgeClass = match ($this) {
            self::Submitted, self::RmbVerification, self::RmbReceived => 'bg-yellow-100 text-yellow-800',
            self::PayoutProcessing, self::Paid => 'bg-blue-100 text-blue-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Rejected, self::Failed => 'bg-red-100 text-red-800',
            self::Cancelled => 'bg-gray-100 text-gray-800',
        };

        return [
            'header_title' => $this->buyerHeaderTitle(),
            'header_subtitle' => $this->buyerHeaderSubtitle(),
            'header_color' => $this->buyerHeaderColor(),
            'badge_label' => $this->buyerBadgeLabel(),
            'badge_class' => $badgeClass,
        ];
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
