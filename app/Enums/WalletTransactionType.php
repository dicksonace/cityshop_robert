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
}
