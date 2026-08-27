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

    case ConvertGhsToRmb = 'convert_ghs_to_rmb';
    case ConvertRmbToGhs = 'convert_rmb_to_ghs';
    case RmbTransferOut = 'rmb_transfer_out';
    case RmbTransferRefund = 'rmb_transfer_refund';
    case RmbFundAdded = 'rmb_fund_added';
    case RmbFundRemoved = 'rmb_fund_removed';
    case ChinaTransferDebit = 'china_transfer_debit';
    case ChinaTransferRefund = 'china_transfer_refund';

    /** Mirrors the labels the web wallet shows, for API clients. */
    public function label(): string
    {
        return match ($this) {
            self::SalePending => 'Sale (Pending)',
            self::SaleReleased => 'Funds Released',
            self::Withdrawal => 'Withdrawal · Processing',
            self::WithdrawalCompleted => 'Withdrawal · Completed',
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
            self::ConvertGhsToRmb => 'Convert GHS → RMB',
            self::ConvertRmbToGhs => 'Convert RMB → GHS',
            self::RmbTransferOut => 'RMB Transfer · Held',
            self::RmbTransferRefund => 'RMB Transfer Refunded',
            self::RmbFundAdded => 'RMB Credited',
            self::RmbFundRemoved => 'RMB Debited',
            self::ChinaTransferDebit => 'Buy RMB · Wallet',
            self::ChinaTransferRefund => 'Buy RMB · Refund',
        };
    }

    public function isRmbLedger(): bool
    {
        return in_array($this, [
            self::ConvertGhsToRmb,
            self::ConvertRmbToGhs,
            self::RmbTransferOut,
            self::RmbTransferRefund,
            self::RmbFundAdded,
            self::RmbFundRemoved,
        ], true);
    }
}
