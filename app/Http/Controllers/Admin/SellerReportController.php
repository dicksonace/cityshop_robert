<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerReportStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerReport;
use App\Services\SellerAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SellerReportController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status', 'open');

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
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/seller-reports/index', [
            'reports' => $reports,
            'status' => $status,
        ]);
    }

    public function update(Request $request, SellerReport $report): RedirectResponse
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
                    return back()->with('error', $e->getMessage());
                }
            }
        }

        return back()->with('success', 'Report updated.');
    }
}
