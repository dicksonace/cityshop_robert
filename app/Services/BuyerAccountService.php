<?php

namespace App\Services;

use App\Enums\ChinaTransferStatus;
use App\Enums\GsmOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SellRmbStatus;
use App\Enums\WalletTopUpStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Checkout;
use App\Models\ChinaTransfer;
use App\Models\OrderItem;
use App\Models\SellRmbTransfer;
use App\Models\User;
use App\Models\WalletTopUpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BuyerAccountService
{
    public function block(User $buyer, string $reason): void
    {
        if (! $buyer->isBuyer()) {
            throw new InvalidArgumentException('Only buyer accounts can be blocked here.');
        }

        if ($buyer->isBlocked()) {
            throw new InvalidArgumentException('This buyer is already blocked.');
        }

        $buyer->update([
            'blocked_at' => now(),
            'block_reason' => $reason,
        ]);

        $buyer->tokens()->delete();
    }

    public function unblock(User $buyer): void
    {
        if (! $buyer->isBuyer()) {
            throw new InvalidArgumentException('Only buyer accounts can be unblocked here.');
        }

        if (! $buyer->isBlocked()) {
            throw new InvalidArgumentException('This buyer is not blocked.');
        }

        $buyer->update([
            'blocked_at' => null,
            'block_reason' => null,
        ]);
    }

    public function delete(User $buyer, ?string $reason = null): void
    {
        if ($buyer->isAdmin() || (! $buyer->isBuyer() && ! $buyer->isSeller())) {
            throw new InvalidArgumentException('This account cannot be deleted here.');
        }

        DB::transaction(function () use ($buyer, $reason) {
            $buyer->tokens()->delete();

            if ($reason) {
                $buyer->forceFill(['block_reason' => $reason]);
            }

            $buyer->releaseLoginIdentifiers();
            $buyer->save();
            $buyer->delete();
        });
    }

    /**
     * Reasons a buyer cannot close their own account yet.
     *
     * @return list<string>
     */
    public function selfDeletionBlockers(User $buyer): array
    {
        if ($buyer->isAdmin() || (! $buyer->isBuyer() && ! $buyer->isSeller())) {
            return ['This account cannot be deleted in the app. Contact support at cityunlock.net/contact.'];
        }

        $blockers = [];
        $wallet = WalletService::ensure($buyer);
        $available = (float) $wallet->available_balance;
        $pending = (float) $wallet->pending_balance;
        $rmb = (float) $wallet->rmb_balance;

        if ($available > 0.009) {
            $blockers[] = 'Wallet still has GH₵'.number_format($available, 2).'. Withdraw or spend it first.';
        }
        if ($pending > 0.009) {
            $blockers[] = 'You have GH₵'.number_format($pending, 2).' pending in your wallet. Wait until it is released.';
        }
        if ($rmb > 0.009) {
            $blockers[] = 'RMB wallet still has ¥'.number_format($rmb, 2).'. Convert or use it first.';
        }

        $openOrders = $buyer->orders()
            ->whereNotIn('status', [
                OrderStatus::Delivered,
                OrderStatus::Cancelled,
                OrderStatus::Refunded,
            ])
            ->count();
        if ($openOrders > 0) {
            $blockers[] = $openOrders === 1
                ? 'You have an order that is still processing. Wait until it is delivered, cancelled, or refunded.'
                : "You have {$openOrders} orders that are still processing. Wait until they are finished.";
        }

        $openCheckouts = Checkout::query()
            ->where('buyer_id', $buyer->id)
            ->whereIn('payment_status', [PaymentStatus::Pending, PaymentStatus::Partial])
            ->whereNotIn('status', [
                OrderStatus::Delivered,
                OrderStatus::Cancelled,
                OrderStatus::Refunded,
            ])
            ->count();
        if ($openCheckouts > 0) {
            $blockers[] = 'You have a checkout that is still unpaid or processing.';
        }

        $openWithdrawals = $buyer->withdrawals()
            ->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Processing])
            ->count();
        if ($openWithdrawals > 0) {
            $blockers[] = 'You have a withdrawal that is still processing.';
        }

        $openTopUps = WalletTopUpRequest::query()
            ->where('user_id', $buyer->id)
            ->where('status', WalletTopUpStatus::Pending)
            ->count();
        if ($openTopUps > 0) {
            $blockers[] = 'You have a wallet top-up still under review.';
        }

        $openChina = ChinaTransfer::query()
            ->where('user_id', $buyer->id)
            ->whereIn('status', array_values(array_filter(
                ChinaTransferStatus::cases(),
                fn (ChinaTransferStatus $status) => $status->isOpen(),
            )))
            ->count();
        if ($openChina > 0) {
            $blockers[] = 'You have a China / RMB transfer that is still processing.';
        }

        $openSellRmb = SellRmbTransfer::query()
            ->where('user_id', $buyer->id)
            ->whereIn('status', array_values(array_filter(
                SellRmbStatus::cases(),
                fn (SellRmbStatus $status) => $status->isOpen(),
            )))
            ->count();
        if ($openSellRmb > 0) {
            $blockers[] = 'You have a sell RMB request that is still processing.';
        }

        $openGsm = $buyer->gsmOrders()
            ->whereNotIn('status', [
                GsmOrderStatus::Completed,
                GsmOrderStatus::Cancelled,
                GsmOrderStatus::Failed,
            ])
            ->count();
        if ($openGsm > 0) {
            $blockers[] = 'You have a GSM Tools order that is still processing.';
        }

        if ($buyer->isSeller()) {
            $openSellerOrders = OrderItem::query()
                ->where('seller_id', $buyer->id)
                ->whereNotIn('status', [
                    OrderStatus::Delivered,
                    OrderStatus::Cancelled,
                    OrderStatus::Refunded,
                ])
                ->count();
            if ($openSellerOrders > 0) {
                $blockers[] = $openSellerOrders === 1
                    ? 'You have a seller order that is still open. Finish, cancel, or refund it first.'
                    : "You have {$openSellerOrders} seller orders that are still open. Finish them first.";
            }
        }

        return $blockers;
    }

    /**
     * @return array{can_delete: bool, blockers: list<string>}
     */
    public function selfDeletionStatus(User $buyer): array
    {
        $blockers = $this->selfDeletionBlockers($buyer);

        return [
            'can_delete' => $blockers === [],
            'blockers' => $blockers,
        ];
    }

    public function assertCanSelfDelete(User $buyer): void
    {
        $blockers = $this->selfDeletionBlockers($buyer);
        if ($blockers === []) {
            return;
        }

        throw ValidationException::withMessages([
            'account' => $blockers,
        ]);
    }

    public function selfDelete(User $buyer): void
    {
        if (! $buyer->isBuyer() && ! $buyer->isSeller()) {
            throw new InvalidArgumentException('This account cannot be deleted here.');
        }

        $this->assertCanSelfDelete($buyer);
        $this->delete($buyer, 'User deleted their own account.');
    }
}
