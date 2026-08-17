<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Models\SellerCoupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coupons = SellerCoupon::where('seller_id', $request->user()->id)
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $coupons->getCollection()->map(fn (SellerCoupon $coupon) => $this->serialize($coupon))->values(),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'total' => $coupons->total(),
            ],
            'types' => collect(CouponType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => match ($t) {
                    CouponType::Percentage => 'Percentage off',
                    CouponType::Fixed => 'Fixed amount off',
                    CouponType::FreeShipping => 'Free shipping',
                },
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash'],
            'type' => ['required', 'in:percentage,fixed,free_shipping'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ]);

        if ($validated['type'] === 'percentage' && $validated['value'] > 100) {
            return response()->json([
                'message' => 'Percentage cannot exceed 100.',
                'errors' => ['value' => ['Percentage cannot exceed 100.']],
            ], 422);
        }

        $coupon = SellerCoupon::create([
            ...$validated,
            'seller_id' => $request->user()->id,
            'code' => strtoupper($validated['code']),
            'min_order_amount' => $validated['min_order_amount'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Coupon created.',
            'data' => $this->serialize($coupon),
        ], 201);
    }

    public function update(Request $request, SellerCoupon $coupon): JsonResponse
    {
        abort_unless($coupon->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'is_active' => ['boolean'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $coupon->update($validated);

        return response()->json([
            'message' => 'Coupon updated.',
            'data' => $this->serialize($coupon->fresh()),
        ]);
    }

    public function destroy(Request $request, SellerCoupon $coupon): JsonResponse
    {
        abort_unless($coupon->seller_id === $request->user()->id, 403);
        $coupon->delete();

        return response()->json(['message' => 'Coupon deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SellerCoupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type->value,
            'value' => (float) $coupon->value,
            'min_order_amount' => (float) $coupon->min_order_amount,
            'max_uses' => $coupon->max_uses,
            'used_count' => (int) $coupon->used_count,
            'starts_at' => $coupon->starts_at?->toIso8601String(),
            'ends_at' => $coupon->ends_at?->toIso8601String(),
            'is_active' => (bool) $coupon->is_active,
            'is_valid' => $coupon->isValidNow(),
        ];
    }
}
