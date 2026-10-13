<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\KorapayWebhookController;
use App\Models\KorapayPayment;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KorapayPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();

        return Inertia::render('admin/korapay-payments/index', [
            'payments' => self::paginate($request, 30),
            'search' => $search !== '' ? $search : null,
            'status' => $status !== '' ? $status : 'all',
            'korapay' => PlatformSettings::korapayKeysStatus(),
            'totals' => self::totals(),
        ]);
    }

    public function recheck(KorapayPayment $korapayPayment, KorapayWebhookController $settler): RedirectResponse
    {
        [$ok, $message] = self::settle($korapayPayment, $settler);

        return back()->with($ok ? 'success' : 'error', $message);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public static function paginate(Request $request, int $perPage): LengthAwarePaginator
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();

        $payments = KorapayPayment::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhereIn('user_id', User::query()
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%")
                            ->select('id'));
                });
            })
            ->when($status !== '' && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $users = User::query()
            ->whereIn('id', $payments->getCollection()->pluck('user_id')->filter()->unique())
            ->get(['id', 'name', 'mobile', 'role'])
            ->keyBy('id');

        return $payments->through(function (KorapayPayment $payment) use ($users) {
            $meta = $payment->meta ?? [];
            $user = $users->get($payment->user_id);

            return [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'purpose' => self::purpose($meta),
                'checkout_number' => $meta['checkout_number'] ?? null,
                'created_at' => $payment->created_at?->toIso8601String(),
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'mobile' => $user->mobile,
                    'role' => $user->role?->value,
                ] : null,
            ];
        });
    }

    /** @return array{success: float, count: int} */
    public static function totals(): array
    {
        return [
            'success' => (float) KorapayPayment::where('status', 'success')->sum('amount'),
            'count' => KorapayPayment::where('status', 'success')->count(),
        ];
    }

    /** @return array{0: bool, 1: string} */
    public static function settle(KorapayPayment $payment, KorapayWebhookController $settler): array
    {
        try {
            $status = $settler->settle($payment->reference);
        } catch (\Throwable $e) {
            return [false, 'Korapay check failed: '.$e->getMessage()];
        }

        return [true, match ($status) {
            'success' => 'Korapay confirms this payment. It has been credited or completed.',
            'failed' => 'Korapay says this payment failed. Nothing was credited.',
            default => 'Korapay says this payment is still '.$status.'. Check again later.',
        }];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function purpose(array $meta): string
    {
        return match (true) {
            ($meta['type'] ?? '') === 'qr_direct_pay' => 'Scan to pay',
            ($meta['type'] ?? '') === 'wallet_topup' => ! empty($meta['gsm']) ? 'GSM recharge' : 'Wallet recharge',
            ! empty($meta['draft']) => 'Checkout',
            ! empty($meta['checkout_id']) => 'Order payment',
            default => 'Payment',
        };
    }
}
