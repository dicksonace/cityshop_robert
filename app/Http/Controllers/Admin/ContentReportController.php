<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerReportStatus;
use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContentReportController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->get('status', 'open');

        $reports = ContentReport::query()
            ->with(['reporter:id,name,email,mobile', 'owner:id,name,email,mobile'])
            ->when($status !== 'all', function ($query) use ($status) {
                if ($status === 'open') {
                    $query->whereIn('status', [SellerReportStatus::Open, SellerReportStatus::Reviewing]);
                } else {
                    $query->where('status', $status);
                }
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ContentReport $report) => [
                'id' => $report->id,
                'status' => $report->status?->value,
                'reason' => $report->reason?->value ?? (string) $report->reason,
                'details' => $report->details,
                'admin_notes' => $report->admin_notes,
                'target_type' => $report->target_type?->value,
                'target_id' => $report->target_id,
                'created_at' => $report->created_at?->toIso8601String(),
                'reporter' => $report->reporter ? [
                    'id' => $report->reporter->id,
                    'name' => $report->reporter->name,
                    'email' => $report->reporter->email,
                ] : null,
                'owner' => $report->owner ? [
                    'id' => $report->owner->id,
                    'name' => $report->owner->name,
                    'email' => $report->owner->email,
                ] : null,
            ]);

        return Inertia::render('admin/content-reports/index', [
            'reports' => $reports,
            'status' => $status,
        ]);
    }

    public function update(Request $request, ContentReport $contentReport): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(SellerReportStatus::class)],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = SellerReportStatus::from($validated['status']);
        $closed = in_array($status, [SellerReportStatus::Resolved, SellerReportStatus::Dismissed], true);

        $contentReport->update([
            'status' => $status,
            'admin_notes' => $validated['admin_notes'] ?? $contentReport->admin_notes,
            'resolved_by' => $closed ? $request->user()->id : $contentReport->resolved_by,
            'resolved_at' => $closed ? now() : $contentReport->resolved_at,
        ]);

        return back()->with('success', 'Report updated.');
    }
}
