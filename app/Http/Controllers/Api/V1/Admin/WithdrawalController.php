<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WithdrawalPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WithdrawalController extends Controller
{
    public function __construct(private WithdrawalPayoutService $payouts) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status', 'pending')->toString();
        $role = $request->string('role', 'all')->toString();

        $withdrawals = Withdrawal::with(['user:id,name,email,mobile,role'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($role === 'seller', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('role', UserRole::Seller)))
            ->when($role === 'buyer', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('role', UserRole::Buyer)))
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $withdrawals->getCollection()->map(fn (Withdrawal $w) => $this->serialize($w))->values(),
            'meta' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'total' => $withdrawals->total(),
            ],
            'status' => $status,
            'role' => $role,
        ]);
    }

    public function start(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        try {
            $this->payouts->startProcessing($withdrawal, $request->user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Withdrawal marked as processing.',
            'data' => $this->serialize($withdrawal->fresh('user')),
        ]);
    }

    public function approve(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        if (! in_array($withdrawal->status, [WithdrawalStatus::Pending, WithdrawalStatus::Processing], true)) {
            return response()->json(['message' => 'This withdrawal cannot be marked paid.'], 422);
        }

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $proofPath = $request->hasFile('proof')
            ? $request->file('proof')->store('withdrawal-proofs', 'public')
            : null;

        $withdrawal->update([
            'processed_by' => $request->user()->id,
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        $this->payouts->markAsPaid(
            $withdrawal->fresh(),
            'manual',
            $proofPath,
            $validated['admin_notes'] ?? null,
        );

        return response()->json([
            'message' => 'Payout marked complete.',
            'data' => $this->serialize($withdrawal->fresh('user')),
        ]);
    }

    public function reject(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        if (! in_array($withdrawal->status, [WithdrawalStatus::Pending, WithdrawalStatus::Processing], true)) {
            return response()->json(['message' => 'This withdrawal cannot be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $withdrawal->update(['processed_by' => $request->user()->id]);
        $this->payouts->markAsFailed($withdrawal->fresh(), $validated['rejection_reason']);

        return response()->json([
            'message' => 'Withdrawal rejected and funds returned to wallet.',
            'data' => $this->serialize($withdrawal->fresh('user')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Withdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'amount' => (float) $withdrawal->amount,
            'fee' => (float) ($withdrawal->fee ?? 0),
            'momo_number' => $withdrawal->momo_number,
            'account_name' => $withdrawal->account_name,
            'network' => $withdrawal->network,
            'status' => $withdrawal->status?->value ?? (string) $withdrawal->status,
            'proof_url' => $withdrawal->proof_path ? Storage::disk('public')->url($withdrawal->proof_path) : null,
            'created_at' => $withdrawal->created_at?->toIso8601String(),
            'user' => $withdrawal->user ? [
                'id' => $withdrawal->user->id,
                'name' => $withdrawal->user->name,
                'mobile' => $withdrawal->user->mobile,
                'role' => $withdrawal->user->role?->value,
            ] : null,
        ];
    }
}
