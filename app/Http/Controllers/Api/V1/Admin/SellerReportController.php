<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SellerReportStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerReport;
use App\Services\SellerAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status', 'open')->toString();

        $reports = SellerReport::query()
            ->with([
                'reporter:id,name,email,mobile',
                'seller:id,name,email,mobile',
                'seller.sellerProfile:id,user_id,store_name,business_name,slug,status',
                'product:id,name,slug',
            ])
            ->when($status !== 'all', function ($query) use ($status) {
                if ($status === 'open') {
                    $query->whereIn('status', [SellerReportStatus::Open, SellerReportStatus::Reviewing]);
                } else {
                    $query->where('status', $status);
                }
            })
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $reports->getCollection()->map(fn (SellerReport $report) => [
                'id' => $report->id,
                'status' => $report->status?->value,
                'reason' => $report->reason?->value ?? (string) $report->reason,
                'details' => $report->details,
                'admin_notes' => $report->admin_notes,
                'created_at' => $report->created_at?->toIso8601String(),
                'reporter' => $report->reporter ? ['id' => $report->reporter->id, 'name' => $report->reporter->name] : null,
                'seller' => $report->seller ? [
                    'id' => $report->seller->id,
                    'name' => $report->seller->name,
                    'store' => $report->seller->sellerProfile?->store_name,
                ] : null,
                'product' => $report->product ? ['id' => $report->product->id, 'name' => $report->product->name] : null,
            ])->values(),
            'meta' => AdminJson::meta($reports),
            'status' => $status,
        ]);
    }

    public function update(Request $request, SellerReport $report): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(SellerReportStatus::class)],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'block_seller' => ['sometimes', 'boolean'],
        ]);

        $status = SellerReportStatus::from($validated['status']);

        $report->update([
            'status' => $status,
            'admin_notes' => $validated['admin_notes'] ?? $report->admin_notes,
            'resolved_by' => in_array($status, [SellerReportStatus::Resolved, SellerReportStatus::Dismissed], true)
                ? $request->user()->id
                : $report->resolved_by,
            'resolved_at' => in_array($status, [SellerReportStatus::Resolved, SellerReportStatus::Dismissed], true)
                ? now()
                : $report->resolved_at,
        ]);

        if ($request->boolean('block_seller')) {
            $report->loadMissing('seller.sellerProfile');
            $profile = $report->seller?->sellerProfile;
            if ($profile) {
                try {
                    app(SellerAccountService::class)->block(
                        $profile,
                        $validated['admin_notes'] ?? 'Blocked after buyer report #'.$report->id,
                    );
                } catch (\InvalidArgumentException $e) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }
            }
        }

        return response()->json(['message' => 'Report updated.']);
    }
}
