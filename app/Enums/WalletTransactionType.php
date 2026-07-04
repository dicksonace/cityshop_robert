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
    case OrderPayment = 'order_payment';
    case OrderRefund = 'order_refund';
    case SaleReversed = 'sale_reversed';
}
