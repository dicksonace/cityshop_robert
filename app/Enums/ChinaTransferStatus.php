<?php

namespace App\Enums;

enum ChinaTransferStatus: string
{
    case PendingPayment = 'pending_payment';
    case PaymentSubmitted = 'payment_submitted';
    case PaymentVerification = 'payment_verification';
    case Processing = 'processing';
    case RmbSent = 'rmb_sent';
    case Completed = 'completed';
    case PaymentFailed = 'payment_failed';
    case PaymentRejected = 'payment_rejected';
    case TransferFailed = 'transfer_failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::PaymentSubmitted => 'Payment submitted',
            self::PaymentVerification => 'Payment verification',
            self::Processing => 'Processing transfer',
            self::RmbSent => 'RMB sent',
            self::Completed => 'Completed',
            self::PaymentFailed => 'Payment failed',
            self::PaymentRejected => 'Payment rejected',
            self::TransferFailed => 'Transfer failed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::PendingPayment,
            self::PaymentSubmitted,
            self::PaymentVerification,
            self::Processing,
            self::RmbSent,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::PaymentFailed,
            self::PaymentRejected,
            self::TransferFailed,
            self::Cancelled,
            self::Refunded,
        ], true);
    }

    public function isImmutable(): bool
    {
        return $this === self::Completed || $this === self::Refunded;
    }

    /** @return list<self> */
    public static function timeline(): array
    {
        return [
            self::PendingPayment,
            self::PaymentSubmitted,
            self::PaymentVerification,
            self::Processing,
            self::RmbSent,
            self::Completed,
        ];
    }
}
