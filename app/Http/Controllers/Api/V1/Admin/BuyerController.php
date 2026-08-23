<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAccountProfileRequest;
use App\Models\User;
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
        $buyer->loadCount('orders');

        return response()->json([
            'data' => $this->serialize($buyer, detailed: true),
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
     * @return array<string, mixed>
     */
    private function serialize(User $buyer, bool $detailed = false): array
    {
        $wallet = $buyer->relationLoaded('wallet') ? $buyer->wallet : ($detailed ? WalletService::ensure($buyer) : $buyer->wallet);

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
            $payload['region'] = $buyer->region;
            $payload['city'] = $buyer->city;
            $payload['block_reason'] = $buyer->block_reason;
            $payload['blocked_at'] = $buyer->blocked_at?->toIso8601String();
        }

        return $payload;
    }
}
