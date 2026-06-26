<?php

namespace App\Http\Controllers\Seller;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Models\SellerCoupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $coupons = SellerCoupon::where('seller_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return Inertia::render('seller/promotions/index', [
            'coupons' => $coupons,
            'types' => collect(CouponType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => match ($t) {
                    CouponType::Percentage => 'Percentage off',
                    CouponType::Fixed => 'Fixed amount off',
                    CouponType::FreeShipping => 'Free shipping',
                },
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
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
            return back()->withErrors(['value' => 'Percentage cannot exceed 100.']);
        }

        SellerCoupon::create([
            ...$validated,
            'seller_id' => $request->user()->id,
            'code' => strtoupper($validated['code']),
            'min_order_amount' => $validated['min_order_amount'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return back()->with('success', 'Coupon created.');
    }

    public function update(Request $request, SellerCoupon $coupon): RedirectResponse
    {
        abort_unless($coupon->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'is_active' => ['boolean'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $coupon->update($validated);

        return back()->with('success', 'Coupon updated.');
    }

    public function destroy(Request $request, SellerCoupon $coupon): RedirectResponse
    {
        abort_unless($coupon->seller_id === $request->user()->id, 403);

        $coupon->delete();

        return back()->with('success', 'Coupon deleted.');
    }
}
