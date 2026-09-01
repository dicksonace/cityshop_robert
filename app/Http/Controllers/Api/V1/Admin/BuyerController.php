<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAccountProfileRequest;
use App\Models\KycVerification;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BuyerAccountService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        $buyers = User::query()
            ->where('role', UserRole::Buyer)
            ->with('wallet')
            ->withCount('orders')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $buyers->getCollection()->map(fn (User $buyer) => $this->serialize($buyer))->values(),
            'meta' => AdminJson::meta($buyers),
        ]);
    }

    public function show(User $buyer): JsonResponse
    {
        abort_unless($buyer->isBuyer(), 404);
        $buyer->load(['latestKyc', 'wallet']);
        $buyer->loadCount(['orders', 'buyerAddresses']);

        $wallet = WalletService::ensure($buyer);

        $recentTransactions = WalletTransaction::query()
            ->where('user_id', $buyer->id)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (WalletTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type?->value,
                'type_label' => $tx->type?->label(),
                'amount' => (float) $tx->amount,
                'description' => $tx->description,
                'reference' => $tx->reference,
                'created_at' => $tx->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $recentOrders = $buyer->orders()
            ->latest()
            ->limit(8)
            ->get(['id', 'order_number', 'total', 'status', 'payment_status', 'created_at'])
            ->map(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total' => (float) $order->total,
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status,
                'payment_status' => $order->payment_status,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => $this->serialize(
                $buyer,
                detailed: true,
                wallet: $wallet,
                recentTransactions: $recentTransactions,
                recentOrders: $recentOrders,
            ),
        ]);
    }

    public function update(UpdateAccountProfileRequest $request, User $buyer): JsonResponse
    {
        abort_unless($buyer->isBuyer(), 404);
        $validated = $request->validated();
        $buyer->updateAccountDetails($validated['name'], $validated['email'], $validated['mobile']);

        return response()->json([
            'message' => 'Buyer account details updated.',
            'data' => $this->serialize($buyer->fresh('wallet'), detailed: true),
        ]);
    }

    public function block(Request $request, User $buyer, BuyerAccountService $accounts): JsonResponse
    {
        abort_unless($buyer->isBuyer(), 404);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $accounts->block($buyer, $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Buyer blacklisted for security.',
            'data' => $this->serialize($buyer->fresh('wallet'), detailed: true),
        ]);
    }

    public function unblock(User $buyer, BuyerAccountService $accounts): JsonResponse
    {
        abort_unless($buyer->isBuyer(), 404);

        try {
            $accounts->unblock($buyer);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Blacklist removed.',
            'data' => $this->serialize($buyer->fresh('wallet'), detailed: true),
        ]);
    }

    public function destroy(Request $request, User $buyer, BuyerAccountService $accounts): JsonResponse
    {
        abort_unless($buyer->isBuyer(), 404);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'confirm_email' => ['required', 'string'],
        ]);

        if (strcasecmp(trim($validated['confirm_email']), trim((string) $buyer->email)) !== 0) {
            return response()->json(['message' => 'Email confirmation did not match. Account was not deleted.'], 422);
        }

        try {
            $accounts->delete($buyer, $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Buyer account deleted. Email and phone can register again.']);
    }

    /**
     * @param  list<array<string, mixed>>|null  $recentTransactions
     * @param  list<array<string, mixed>>|null  $recentOrders
     * @return array<string, mixed>
     */
    private function serialize(
        User $buyer,
        bool $detailed = false,
        ?\App\Models\Wallet $wallet = null,
        ?array $recentTransactions = null,
        ?array $recentOrders = null,
    ): array {
        $wallet = $wallet ?? ($buyer->relationLoaded('wallet') ? $buyer->wallet : ($detailed ? WalletService::ensure($buyer) : $buyer->wallet));

        $payload = [
            'id' => $buyer->id,
            'name' => $buyer->name,
            'email' => $buyer->email,
            'mobile' => $buyer->mobile,
            'created_at' => $buyer->created_at?->toIso8601String(),
            'orders_count' => (int) ($buyer->orders_count ?? 0),
            'available_balance' => (float) ($wallet?->available_balance ?? 0),
            'is_blocked' => $buyer->isBlocked(),
        ];

        if ($detailed) {
            $payload['country'] = $buyer->country;
            $payload['region'] = $buyer->region;
            $payload['city'] = $buyer->city;
            $payload['residential_address'] = $buyer->residential_address;
            $payload['digital_address'] = $buyer->digital_address;
            $payload['ghana_card_number'] = $buyer->ghana_card_number;
            $payload['has_payment_pin'] = filled($buyer->payment_pin);
            $payload['last_seen_at'] = $buyer->last_seen_at?->toIso8601String();
            $payload['addresses_count'] = (int) ($buyer->buyer_addresses_count ?? 0);
            $payload['block_reason'] = $buyer->block_reason;
            $payload['blocked_at'] = $buyer->blocked_at?->toIso8601String();
            $payload['wallet'] = $wallet ? $wallet->toFrontendArray() : [
                'available_balance' => 0.0,
                'pending_balance' => 0.0,
                'total_earnings' => 0.0,
                'withdrawn_amount' => 0.0,
                'rmb_balance' => 0.0,
            ];
            $payload['kyc'] = $this->kycPayload($buyer->relationLoaded('latestKyc') ? $buyer->latestKyc : $buyer->latestKyc()->first());
            $payload['recent_transactions'] = $recentTransactions ?? [];
            $payload['recent_orders'] = $recentOrders ?? [];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function kycPayload(?KycVerification $kyc): ?array
    {
        if (! $kyc) {
            return null;
        }

        return [
            'id' => $kyc->id,
            'status' => $kyc->status?->value,
            'status_label' => $kyc->status?->label(),
            'ghana_card_number' => $kyc->ghana_card_number,
            'full_name' => $kyc->full_name,
            'submitted_at' => $kyc->submitted_at?->toIso8601String() ?? $kyc->created_at?->toIso8601String(),
            'reviewed_at' => $kyc->reviewed_at?->toIso8601String(),
            'front_url' => $kyc->publicUrl($kyc->front_path),
            'back_url' => $kyc->publicUrl($kyc->back_path),
        ];
    }
}
