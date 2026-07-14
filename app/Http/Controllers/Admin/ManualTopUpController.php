<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WalletTopUpStatus;
use App\Http\Controllers\Controller;
use App\Models\WalletTopUpRequest;
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

        $requests = WalletTopUpRequest::with(['user:id,name,email,role,mobile'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (WalletTopUpRequest $item) => [
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
            ]);

        return Inertia::render('admin/manual-funding/top-ups', [
            'requests' => $requests,
            'status' => $status,
            'counts' => [
                'pending' => WalletTopUpRequest::where('status', WalletTopUpStatus::Pending)->count(),
                'approved' => WalletTopUpRequest::where('status', WalletTopUpStatus::Approved)->count(),
                'rejected' => WalletTopUpRequest::where('status', WalletTopUpStatus::Rejected)->count(),
            ],
        ]);
    }

    public function approve(Request $request, WalletTopUpRequest $topUp): RedirectResponse
    {
        if ($topUp->status !== WalletTopUpStatus::Pending) {
            return back()->with('error', 'This request has already been processed.');
        }

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $credited = DB::transaction(function () use ($request, $topUp, $validated) {
            $locked = WalletTopUpRequest::whereKey($topUp->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== WalletTopUpStatus::Pending) {
                return false;
            }

            $reference = 'MANUAL-'.$locked->id.'-'.preg_replace('/\s+/', '', $locked->payment_reference);

            $ok = WalletService::creditFromVerifiedTopUp(
                $locked->user_id,
                (float) $locked->amount,
                $reference,
                'manual',
            );

            if (! $ok) {
                throw new \RuntimeException('Could not credit wallet (duplicate reference).');
            }

            $locked->update([
                'status' => WalletTopUpStatus::Approved,
                'admin_notes' => $validated['admin_notes'] ?? null,
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
            'Approved. GH₵'.number_format((float) $topUp->amount, 2).' credited to the user’s wallet.',
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

        return back()->with('success', 'Top-up request rejected.');
    }
}
