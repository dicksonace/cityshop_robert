<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case SalePending = 'sale_pending';
    case SaleReleased = 'sale_released';
    case Withdrawal = 'withdrawal';
    case WithdrawalCompleted = 'withdrawal_completed';
    case WithdrawalRefunded = 'withdrawal_refunded';
    case FundAdded = 'fund_added';
    case FundRemoved = 'fund_removed';
    case OrderPayment = 'order_payment';
    case OrderRefund = 'order_refund';
    case SaleReversed = 'sale_reversed';
    /** Seller CityShop wallet clawback when a paid pay-to-seller order is cancelled. */
    case DirectCancelDebit = 'direct_cancel_debit';
    /** Peer-to-peer wallet send (chat transfer). */
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    /** Annual seller service / activation fee. */
    case ServiceFee = 'service_fee';

    /** Mirrors the labels the web wallet shows, for API clients. */
    public function label(): string
    {
        return match ($this) {
            self::SalePending => 'Sale (Pending)',
            self::SaleReleased => 'Funds Released',
            self::Withdrawal => 'Withdrawal Request',
            self::WithdrawalCompleted => 'Payout Sent',
            self::WithdrawalRefunded => 'Withdrawal Refunded',
            self::FundAdded => 'Funds Credited',
            self::FundRemoved => 'Funds Debited',
            self::OrderPayment => 'Order Payment',
            self::OrderRefund => 'Order Refund',
            self::SaleReversed => 'Sale Reversed',
            self::DirectCancelDebit => 'Pay-to-seller Cancel',
            self::TransferOut => 'Money Sent',
            self::TransferIn => 'Money Received',
            self::ServiceFee => 'Seller Service Fee',
        };
    }
}
