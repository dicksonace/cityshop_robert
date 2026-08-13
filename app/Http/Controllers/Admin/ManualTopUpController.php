<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WalletTopUpStatus;
use App\Http\Controllers\Controller;
use App\Models\WalletTopUpRequest;
use App\Notifications\WalletTopUpRejectedNotification;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ManualTopUpController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status', 'pending');
        $search = trim((string) $request->get('q', ''));

        $requests = WalletTopUpRequest::with(['user:id,name,email,role,mobile'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    })->orWhere('payment_reference', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $inner->orWhere('id', (int) $search);
                    }
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (WalletTopUpRequest $item) => $this->payload($item));

        $pendingAmount = (float) WalletTopUpRequest::where('status', WalletTopUpStatus::Pending)->sum('amount');

        return Inertia::render('admin/manual-funding/top-ups', [
            'requests' => $requests,
            'status' => $status,
            'search' => $search,
            'pendingTotal' => $pendingAmount,
            'counts' => [
                'pending' => WalletTopUpRequest::where('status', WalletTopUpStatus::Pending)->count(),
                'approved' => WalletTopUpRequest::where('status', WalletTopUpStatus::Approved)->count(),
                'rejected' => WalletTopUpRequest::where('status', WalletTopUpStatus::Rejected)->count(),
                'cancelled' => WalletTopUpRequest::where('status', WalletTopUpStatus::Cancelled)->count(),
            ],
        ]);
    }

    public function updateAmount(Request $request, WalletTopUpRequest $topUp): RedirectResponse
    {
        if ($topUp->status !== WalletTopUpStatus::Pending) {
            return back()->with('error', 'Only pending deposits can be edited.');
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

        return back()->with('success', 'Deposit amount updated to GH₵'.number_format($new, 2).'.');
    }

    public function approve(Request $request, WalletTopUpRequest $topUp): RedirectResponse
    {
        if ($topUp->status !== WalletTopUpStatus::Pending) {
            return back()->with('error', 'This request has already been processed.');
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
            $reference = 'MANUAL-'.$locked->id.'-'.$refPart;

            $ok = WalletService::creditFromVerifiedTopUp(
                $locked->user_id,
                $creditedAmount,
                $reference,
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
            return back()->with('error', 'This request has already been processed.');
        }

        return back()->with(
            'success',
            'Approved. GH₵'.number_format((float) $creditedAmount, 2).' credited to the user’s wallet.',
        );
    }

    public function reject(Request $request, WalletTopUpRequest $topUp): RedirectResponse
    {
        if ($topUp->status !== WalletTopUpStatus::Pending) {
            return back()->with('error', 'This request has already been processed.');
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

        return back()->with('success', 'Top-up request rejected.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(WalletTopUpRequest $item): array
    {
        return [
            'id' => $item->id,
            'amount' => (float) $item->amount,
            'payment_reference' => $item->payment_reference,
            'sender_name' => $item->sender_name,
            'sender_number' => $item->sender_number,
            'network' => $item->network,
            'proof_path' => $item->proof_path,
            'proof_url' => $item->proof_path ? Storage::disk('public')->url($item->proof_path) : null,
            'user_note' => $item->user_note,
            'status' => $item->status->value,
            'admin_notes' => $item->admin_notes,
            'created_at' => $item->created_at?->toIso8601String(),
            'reviewed_at' => $item->reviewed_at?->toIso8601String(),
            'user' => $item->user ? [
                'id' => $item->user->id,
                'name' => $item->user->name,
                'email' => $item->user->email,
                'mobile' => $item->user->mobile,
                'role' => $item->user->role->value,
            ] : null,
        ];
    }
}
