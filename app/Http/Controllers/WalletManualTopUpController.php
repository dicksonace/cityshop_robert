<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\WalletTopUpStatus;
use App\Models\WalletTopUpRequest;
use App\Services\AdminNotifier;
use App\Services\KycService;
use App\Services\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WalletManualTopUpController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $settings = PlatformSettings::manualFundingAccounts();

        if (! $settings['enabled'] || count($settings['accounts']) === 0) {
            return $this->backToWallet($user->role)
                ->with('error', 'Manual top-up is not available right now. Use online payment or contact support.');
        }

        $page = $user->isSeller()
            ? 'seller/wallet/manual-top-up'
            : 'shop/wallet/manual-top-up';

        return Inertia::render($page, [
            'settings' => $settings,
            'requests' => $this->mapRequests($user->id),
            'walletRoute' => $user->isSeller() ? route('seller.wallet') : route('wallet.index'),
            'statusRouteName' => $user->isSeller()
                ? 'seller.wallet.manual-top-up.show'
                : 'wallet.manual-top-up.show',
            'cancelRouteName' => $user->isSeller()
                ? 'seller.wallet.manual-top-up.cancel'
                : 'wallet.manual-top-up.cancel',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        if ($denied = KycService::denyStoreFundsRedirect($user)) {
            return $denied;
        }

        $settings = PlatformSettings::manualFundingAccounts();

        if (! $settings['enabled'] || count($settings['accounts']) === 0) {
            return $this->backToWallet($user->role)
                ->with('error', 'Manual top-up is not available right now.');
        }

        $pending = WalletTopUpRequest::where('user_id', $user->id)
            ->where('status', WalletTopUpStatus::Pending)
            ->exists();

        if ($pending) {
            return back()->with('error', 'You already have a pending manual top-up. Wait for admin review before submitting another.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10', 'max:500000'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'network' => ['required', 'string', 'in:mtn,telecel,airteltigo'],
            'user_note' => ['nullable', 'string', 'max:500'],
            'proof' => ['required', 'image', 'max:5120'],
        ]);

        $proofPath = $request->file('proof')->store('wallet-top-up-proofs', 'public');

        $topUp = WalletTopUpRequest::create([
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'payment_reference' => trim((string) ($validated['payment_reference'] ?? '')),
            'sender_name' => null,
            'sender_number' => null,
            'network' => $validated['network'],
            'proof_path' => $proofPath,
            'user_note' => $validated['user_note'] ?? null,
            'status' => WalletTopUpStatus::Pending,
        ]);

        try {
            AdminNotifier::depositProof($user, $topUp);
        } catch (\Throwable $e) {
            report($e);
        }

        $redirect = $user->isSeller()
            ? redirect()->route('seller.wallet.manual-top-up.show', $topUp)
            : redirect()->route('wallet.manual-top-up.show', $topUp);

        return $redirect->with('success', 'Payment proof submitted. We will credit your wallet after verification.');
    }

    public function showRequest(Request $request, WalletTopUpRequest $topUp): Response|RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (int) $topUp->user_id === (int) $user->id, 403);

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json(['data' => $this->mapRequest($topUp)]);
        }

        $page = $user->isSeller()
            ? 'seller/wallet/manual-top-up-status'
            : 'shop/wallet/manual-top-up-status';

        return Inertia::render($page, [
            'request' => $this->mapRequest($topUp),
            'walletRoute' => $user->isSeller() ? route('seller.wallet') : route('wallet.index'),
            'historyRoute' => $user->isSeller()
                ? route('seller.wallet.manual-top-up')
                : route('wallet.manual-top-up'),
            'cancelRoute' => $user->isSeller()
                ? route('seller.wallet.manual-top-up.cancel', $topUp)
                : route('wallet.manual-top-up.cancel', $topUp),
            'pollUrl' => $user->isSeller()
                ? route('seller.wallet.manual-top-up.show', ['topUp' => $topUp->id, 'json' => 1])
                : route('wallet.manual-top-up.show', ['topUp' => $topUp->id, 'json' => 1]),
        ]);
    }

    public function cancel(Request $request, WalletTopUpRequest $topUp): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (int) $topUp->user_id === (int) $user->id, 403);

        if ($topUp->status !== WalletTopUpStatus::Pending) {
            $message = 'Only pending deposits can be cancelled.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $topUp->update([
            'status' => WalletTopUpStatus::Cancelled,
            'admin_notes' => 'Cancelled by user.',
            'reviewed_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Deposit request cancelled.',
                'data' => $this->mapRequest($topUp->fresh()),
            ]);
        }

        $redirect = $user->isSeller()
            ? redirect()->route('seller.wallet.manual-top-up')
            : redirect()->route('wallet.manual-top-up');

        return $redirect->with('success', 'Deposit request cancelled.');
    }

    private function backToWallet(UserRole $role): RedirectResponse
    {
        if ($role === UserRole::Seller) {
            return redirect()->route('seller.wallet');
        }

        return redirect()->route('wallet.index');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapRequests(int $userId): array
    {
        return WalletTopUpRequest::where('user_id', $userId)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (WalletTopUpRequest $item) => $this->mapRequest($item))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRequest(WalletTopUpRequest $item): array
    {
        return [
            'id' => $item->id,
            'amount' => (float) $item->amount,
            'payment_reference' => $item->payment_reference,
            'network' => $item->network,
            'user_note' => $item->user_note,
            'status' => $item->status->value,
            'admin_notes' => $item->admin_notes,
            'proof_url' => $item->proof_path ? Storage::disk('public')->url($item->proof_path) : null,
            'created_at' => $item->created_at?->toIso8601String(),
            'reviewed_at' => $item->reviewed_at?->toIso8601String(),
        ];
    }
}
