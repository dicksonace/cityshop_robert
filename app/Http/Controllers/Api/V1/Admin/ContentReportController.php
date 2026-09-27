<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SellerReportStatus;
use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status', 'open')->toString();

        $reports = $this->query($status)->paginate(20);

        return response()->json([
            'data' => $reports->getCollection()->map(fn (ContentReport $report) => $this->payload($report))->values(),
            'meta' => AdminJson::meta($reports),
            'status' => $status,
        ]);
    }

    public function update(Request $request, ContentReport $contentReport): JsonResponse
    {
        $report = $this->applyUpdate($request, $contentReport);

        return response()->json([
            'report' => $this->payload($report),
            'message' => 'Report updated.',
        ]);
    }

    private function query(string $status)
    {
        return ContentReport::query()
            ->with(['reporter:id,name,email,mobile', 'owner:id,name,email,mobile'])
            ->when($status !== 'all', function ($query) use ($status) {
                if ($status === 'open') {
                    $query->whereIn('status', [SellerReportStatus::Open, SellerReportStatus::Reviewing]);
                } else {
                    $query->where('status', $status);
                }
            })
            ->latest();
    }

    private function applyUpdate(Request $request, ContentReport $report): ContentReport
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(SellerReportStatus::class)],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = SellerReportStatus::from($validated['status']);
        $closed = in_array($status, [SellerReportStatus::Resolved, SellerReportStatus::Dismissed], true);

        $report->update([
            'status' => $status,
            'admin_notes' => $validated['admin_notes'] ?? $report->admin_notes,
            'resolved_by' => $closed ? $request->user()->id : $report->resolved_by,
            'resolved_at' => $closed ? now() : $report->resolved_at,
        ]);

        return $report->fresh(['reporter:id,name,email,mobile', 'owner:id,name,email,mobile']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ContentReport $report): array
    {
        return [
            'id' => $report->id,
            'status' => $report->status?->value,
            'reason' => $report->reason?->value ?? (string) $report->reason,
            'details' => $report->details,
            'admin_notes' => $report->admin_notes,
            'target_type' => $report->target_type?->value,
            'target_id' => $report->target_id,
            'created_at' => $report->created_at?->toIso8601String(),
            'reporter' => $report->reporter ? ['id' => $report->reporter->id, 'name' => $report->reporter->name] : null,
            'owner' => $report->owner ? ['id' => $report->owner->id, 'name' => $report->owner->name] : null,
        ];
    }
}
