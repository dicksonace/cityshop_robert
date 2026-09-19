<?php

namespace App\Enums;

enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function buyerLabel(): string
    {
        return match ($this) {
            self::Pending, self::Processing, self::Approved => 'Processing',
            self::Paid => 'Completed',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * @return array{header_title: string, header_subtitle: string, header_color: string, badge_label: string, badge_class: string}
     */
    public function buyerPresentation(): array
    {
        return match ($this) {
            self::Paid => [
                'header_title' => 'Payout Complete!',
                'header_subtitle' => 'GHS has been sent to your payout account',
                'header_color' => '#22c55e',
                'badge_label' => 'Completed',
                'badge_class' => 'bg-green-100 text-green-800',
            ],
            self::Rejected => [
                'header_title' => 'Withdrawal Rejected',
                'header_subtitle' => 'See details below',
                'header_color' => '#dc2626',
                'badge_label' => 'Rejected',
                'badge_class' => 'bg-red-100 text-red-800',
            ],
            default => [
                'header_title' => 'Processing',
                'header_subtitle' => 'Your withdrawal is being sent',
                'header_color' => '#ef4444',
                'badge_label' => 'Processing',
                'badge_class' => 'bg-yellow-100 text-yellow-800',
            ],
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Processing, self::Approved], true);
    }
}
