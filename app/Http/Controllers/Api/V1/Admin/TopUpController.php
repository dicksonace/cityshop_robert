<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\WalletTopUpStatus;
use App\Http\Controllers\Controller;
use App\Models\WalletTopUpRequest;
use App\Notifications\WalletTopUpRejectedNotification;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TopUpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status', 'pending')->toString();

        $requests = WalletTopUpRequest::with(['user:id,name,email,role,mobile'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $requests->getCollection()->map(fn (WalletTopUpRequest $item) => $this->serialize($item))->values(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
            ],
            'status' => $status,
        ]);
    }

    public function updateAmount(Request $request, WalletTopUpRequest $topUp): JsonResponse
    {
        if ($topUp->status !== WalletTopUpStatus::Pending) {
            return response()->json(['message' => 'Only pending deposits can be edited.'], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:500000'],
        ]);

        $old = (float) $topUp->amount;
        $new = round((float) $validated['amount'], 2);

        $topUp->update([
            'amount' => $new,
            'admin_notes' => trim(($topUp->admin_notes ? $topUp->admin_notes."\n" : '').'Amount edited from GH₵'
                .number_format($old, 2).' to GH₵'.number_format($new, 2).' by admin.'),
        ]);

        return response()->json([
            'message' => 'Deposit amount updated to GH₵'.number_format($new, 2).'.',
            'data' => $this->serialize($topUp->fresh('user')),
        ]);
    }

    public function approve(Request $request, WalletTopUpRequest $topUp): JsonResponse
    {
        if ($topUp->status !== WalletTopUpStatus::Pending) {
            return response()->json(['message' => 'This request has already been processed.'], 422);
        }

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'amount' => ['nullable', 'numeric', 'min:1', 'max:500000'],
        ]);

        $creditedAmount = null;
        $credited = DB::transaction(function () use ($request, $topUp, $validated, &$creditedAmount) {
            $locked = WalletTopUpRequest::whereKey($topUp->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== WalletTopUpStatus::Pending) {
                return false;
            }
            if (isset($validated['amount'])) {
                $locked->amount = round((float) $validated['amount'], 2);
            }
            $creditedAmount = (float) $locked->amount;
            $refPart = preg_replace('/\s+/', '', (string) ($locked->payment_reference ?? '')) ?: 'proof';
            $ok = WalletService::creditFromVerifiedTopUp(
                $locked->user_id,
                $creditedAmount,
                'MANUAL-'.$locked->id.'-'.$refPart,
                'manual',
            );
            if (! $ok) {
                throw new \RuntimeException('Could not credit wallet (duplicate reference).');
            }
            $locked->update([
                'amount' => $creditedAmount,
                'status' => WalletTopUpStatus::Approved,
                'admin_notes' => $validated['admin_notes'] ?? $locked->admin_notes,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            return true;
        });

        if (! $credited) {
            return response()->json(['message' => 'This request has already been processed.'], 422);
        }

        return response()->json([
            'message' => 'Approved. GH₵'.number_format((float) $creditedAmount, 2).' credited.',
            'data' => $this->serialize($topUp->fresh('user')),
        ]);
    }

    public function reject(Request $request, WalletTopUpRequest $topUp): JsonResponse
    {
        if ($topUp->status !== WalletTopUpStatus::Pending) {
            return response()->json(['message' => 'This request has already been processed.'], 422);
        }

        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'max:1000'],
        ]);

        $topUp->update([
            'status' => WalletTopUpStatus::Rejected,
            'admin_notes' => $validated['admin_notes'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        try {
            $topUp->loadMissing('user');
            $topUp->user?->notify(new WalletTopUpRejectedNotification((float) $topUp->amount));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => 'Top-up request rejected.',
            'data' => $this->serialize($topUp->fresh('user')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WalletTopUpRequest $item): array
    {
        return [
            'id' => $item->id,
            'amount' => (float) $item->amount,
            'payment_reference' => $item->payment_reference,
            'sender_name' => $item->sender_name,
            'sender_number' => $item->sender_number,
            'network' => $item->network,
            'proof_url' => $item->proof_path ? Storage::disk('public')->url($item->proof_path) : null,
            'user_note' => $item->user_note,
            'admin_notes' => $item->admin_notes,
            'status' => $item->status?->value ?? (string) $item->status,
            'created_at' => $item->created_at?->toIso8601String(),
            'reviewed_at' => $item->reviewed_at?->toIso8601String(),
            'user' => $item->user ? [
                'id' => $item->user->id,
                'name' => $item->user->name,
                'email' => $item->user->email,
                'mobile' => $item->user->mobile,
                'role' => $item->user->role?->value,
            ] : null,
        ];
    }
}
