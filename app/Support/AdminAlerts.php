<?php

namespace App\Support;

use App\Enums\SellerStatus;
use App\Models\AppNotification;
use App\Models\ChinaTransfer;
use App\Models\Dispute;
use App\Models\GsmOrder;
use App\Models\KycVerification;
use App\Models\SellerProfile;
use App\Models\SellRmbTransfer;
use App\Models\User;
use App\Models\WalletTopUpRequest;
use App\Models\Withdrawal;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * "Needs a decision" alerts on the admin dashboard. An alert goes away once the
 * request it is about has been handled.
 */
class AdminAlerts
{
    /** @var array<class-string<Model>, list<string>> statuses that still need an admin */
    private const OPEN = [
        KycVerification::class => ['pending'],
        SellerProfile::class => ['pending'],
        Withdrawal::class => ['pending', 'processing'],
        WalletTopUpRequest::class => ['pending'],
        Dispute::class => ['open', 'under_review'],
        GsmOrder::class => ['pending', 'processing'],
        ChinaTransfer::class => ['payment_submitted', 'payment_verification', 'processing', 'rmb_sent'],
        SellRmbTransfer::class => ['submitted', 'rmb_verification', 'rmb_received', 'payout_processing', 'paid'],
    ];

    /** @return array{subject_type: class-string<Model>, subject_id: int}|null */
    public static function subjectData(object $notification, ?Model $subject = null): ?array
    {
        $subject ??= collect(get_object_vars($notification))
            ->first(fn ($value) => $value instanceof Model && isset(self::OPEN[$value::class]));

        if (! $subject instanceof Model || ! isset(self::OPEN[$subject::class]) || ! $subject->getKey()) {
            return null;
        }

        return ['subject_type' => $subject::class, 'subject_id' => (int) $subject->getKey()];
    }

    /** @return Collection<int, array{id: int, title: string, body: ?string, created_at: ?string}> */
    public static function forUser(User $user, int $limit = 6): Collection
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'admin_action')
            ->where('created_at', '>=', now()->subDays(2))
            ->latest()
            ->limit(40)
            ->get()
            ->filter(fn (AppNotification $alert) => self::stillOpen($alert))
            ->take($limit)
            ->map(fn (AppNotification $alert) => [
                'id' => $alert->id,
                'title' => $alert->title,
                'body' => $alert->body,
                'created_at' => $alert->created_at?->toIso8601String(),
                'href' => self::hrefFor($alert),
            ])
            ->values();
    }

    private static function stillOpen(AppNotification $alert): bool
    {
        $data = is_array($alert->data) ? $alert->data : [];
        $type = $data['subject_type'] ?? null;

        if (is_string($type) && isset(self::OPEN[$type])) {
            $model = $type::query()->find($data['subject_id'] ?? 0);

            return $model !== null && in_array(self::statusOf($model), self::OPEN[$type], true);
        }

        if (preg_match('/submitted Ghana Card KYC \(([^)]+)\)/', (string) $alert->body, $m)) {
            return KycVerification::query()
                ->where('ghana_card_number', $m[1])
                ->where('status', 'pending')
                ->exists();
        }

        // Older seller-application SMS alerts had no subject link, so they stacked forever.
        if (preg_match('/applied to sell \(([^)]+)\)/', (string) $alert->body, $m)) {
            $store = trim($m[1]);

            return SellerProfile::query()
                ->where('status', SellerStatus::Pending)
                ->where(function ($q) use ($store) {
                    $q->where('store_name', $store)
                        ->orWhere('business_name', $store);
                })
                ->exists();
        }

        return true;
    }

    private static function hrefFor(AppNotification $alert): ?string
    {
        $data = is_array($alert->data) ? $alert->data : [];
        $type = $data['subject_type'] ?? null;

        return match ($type) {
            SellerProfile::class => '/sellers',
            Withdrawal::class, WalletTopUpRequest::class => '/money',
            ChinaTransfer::class => '/china-transfers',
            SellRmbTransfer::class => '/sell-rmb',
            GsmOrder::class => '/gsm-tools',
            KycVerification::class => '/kyc',
            Dispute::class => '/orders',
            default => str_contains((string) $alert->body, 'applied to sell')
                ? '/sellers'
                : null,
        };
    }

    private static function statusOf(Model $model): string
    {
        $status = $model->getAttribute('status');

        return $status instanceof BackedEnum ? (string) $status->value : (string) $status;
    }
}
