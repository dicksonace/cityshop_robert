<?php

namespace App\Services;

use App\Enums\ChinaTransferStatus;
use App\Models\ChinaTransfer;
use App\Models\ChinaTransferSetting;
use App\Models\User;
use App\Models\WalletConversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Shared KYC + velocity guards for convert and Alipay RMB-out.
 */
class RmbWalletGuard
{
    public static function assertCanTransact(User $user): void
    {
        if (! KycService::canStoreFunds($user)) {
            throw ValidationException::withMessages([
                'kyc' => [KycService::denyStoreFundsMessage($user)],
            ]);
        }
    }

    public static function denyJson(User $user): ?JsonResponse
    {
        return KycService::denyStoreFundsResponse($user);
    }

    public static function denyRedirect(User $user): ?RedirectResponse
    {
        return KycService::denyStoreFundsRedirect($user);
    }

    public static function assertConvertVelocity(User $user): void
    {
        $settings = ChinaTransferSetting::current();
        $max = (int) ($settings->max_converts_per_day ?? 30);
        if ($max <= 0) {
            return;
        }

        $count = WalletConversion::query()
            ->where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        if ($count >= $max) {
            throw ValidationException::withMessages([
                'amount' => ["Daily convert limit reached ({$max} per day). Try again tomorrow."],
            ]);
        }
    }

    public static function assertRmbOutLimits(User $user, float $rmbAmount): void
    {
        $settings = ChinaTransferSetting::current();
        $openStatuses = [
            ChinaTransferStatus::PendingPayment,
            ChinaTransferStatus::PaymentSubmitted,
            ChinaTransferStatus::PaymentVerification,
            ChinaTransferStatus::Processing,
            ChinaTransferStatus::RmbSent,
            ChinaTransferStatus::Completed,
        ];

        if ($settings->max_rmb_out_per_day !== null) {
            $daySum = (float) ChinaTransfer::query()
                ->where('user_id', $user->id)
                ->where('funding_source', 'rmb_wallet')
                ->whereDate('created_at', today())
                ->whereIn('status', $openStatuses)
                ->sum('rmb_amount');

            if ($daySum + $rmbAmount > (float) $settings->max_rmb_out_per_day + 0.0001) {
                throw ValidationException::withMessages([
                    'rmb_amount' => [
                        'This exceeds your daily RMB transfer limit of ¥'
                        .number_format((float) $settings->max_rmb_out_per_day, 2).'.',
                    ],
                ]);
            }
        }

        if ($settings->max_rmb_out_per_month !== null) {
            $monthSum = (float) ChinaTransfer::query()
                ->where('user_id', $user->id)
                ->where('funding_source', 'rmb_wallet')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->whereIn('status', $openStatuses)
                ->sum('rmb_amount');

            if ($monthSum + $rmbAmount > (float) $settings->max_rmb_out_per_month + 0.0001) {
                throw ValidationException::withMessages([
                    'rmb_amount' => [
                        'This exceeds your monthly RMB transfer limit of ¥'
                        .number_format((float) $settings->max_rmb_out_per_month, 2).'.',
                    ],
                ]);
            }
        }
    }

    /**
     * @return array{ip_address: ?string, user_agent: ?string}
     */
    public static function requestMeta(?Request $request): array
    {
        if (! $request) {
            return ['ip_address' => null, 'user_agent' => null];
        }

        $agent = (string) $request->userAgent();
        if (strlen($agent) > 512) {
            $agent = substr($agent, 0, 512);
        }

        return [
            'ip_address' => $request->ip(),
            'user_agent' => $agent !== '' ? $agent : null,
        ];
    }
}
