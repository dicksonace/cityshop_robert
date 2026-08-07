<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SellerReportReason;
use App\Enums\SellerReportStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SellerReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'seller_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'reason' => ['required', Rule::enum(SellerReportReason::class)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $sellerId = (int) $validated['seller_id'];

        if ($sellerId === $user->id) {
            return response()->json(['message' => 'You cannot report your own account.'], 422);
        }

        $seller = User::query()->whereKey($sellerId)->where('role', UserRole::Seller)->first();
        if (! $seller) {
            return response()->json(['message' => 'Seller account not found.'], 404);
        }

        $profile = SellerProfile::query()
            ->where('user_id', $sellerId)
            ->where('status', SellerStatus::Approved)
            ->first();

        if (! $profile) {
            return response()->json(['message' => 'This seller cannot be reported right now.'], 422);
        }

        if (! empty($validated['product_id'])) {
            $product = Product::query()->find($validated['product_id']);
            if (! $product || $product->seller_id !== $sellerId) {
                return response()->json(['message' => 'Invalid product for this report.'], 422);
            }
        }

        $alreadyOpen = SellerReport::query()
            ->where('reporter_id', $user->id)
            ->where('seller_id', $sellerId)
            ->whereIn('status', [SellerReportStatus::Open, SellerReportStatus::Reviewing])
            ->exists();

        if ($alreadyOpen) {
            return response()->json([
                'message' => 'You already have an open report for this seller. Our team is reviewing it.',
            ], 422);
        }

        SellerReport::create([
            'reporter_id' => $user->id,
            'seller_id' => $sellerId,
            'product_id' => $validated['product_id'] ?? null,
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
            'status' => SellerReportStatus::Open,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Report submitted. Our team will review this seller account.',
        ], 201);
    }
}
