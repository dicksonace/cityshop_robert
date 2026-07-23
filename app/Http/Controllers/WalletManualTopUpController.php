<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\WalletTopUpStatus;
use App\Models\WalletTopUpRequest;
use App\Services\PlatformSettings;
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

        $requests = WalletTopUpRequest::where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (WalletTopUpRequest $item) => [
                'id' => $item->id,
                'amount' => (float) $item->amount,
                'payment_reference' => $item->payment_reference,
                'status' => $item->status->value,
                'admin_notes' => $item->admin_notes,
                'proof_url' => $item->proof_path ? Storage::disk('public')->url($item->proof_path) : null,
                'created_at' => $item->created_at?->toIso8601String(),
                'reviewed_at' => $item->reviewed_at?->toIso8601String(),
            ]);

        $page = $user->isSeller()
            ? 'seller/wallet/manual-top-up'
            : 'shop/wallet/manual-top-up';

        return Inertia::render($page, [
            'settings' => $settings,
            'requests' => $requests,
            'walletRoute' => $user->isSeller() ? route('seller.wallet') : route('wallet.index'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

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
            'payment_reference' => ['required', 'string', 'max:100'],
            'network' => ['required', 'string', 'in:mtn,telecel,airteltigo'],
            'user_note' => ['nullable', 'string', 'max:500'],
            'proof' => ['required', 'image', 'max:5120'],
        ]);

        $proofPath = $request->file('proof')->store('wallet-top-up-proofs', 'public');

        WalletTopUpRequest::create([
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'payment_reference' => trim($validated['payment_reference']),
            'sender_name' => null,
            'sender_number' => null,
            'network' => $validated['network'],
            'proof_path' => $proofPath,
            'user_note' => $validated['user_note'] ?? null,
            'status' => WalletTopUpStatus::Pending,
        ]);

        $redirect = $user->isSeller()
            ? redirect()->route('seller.wallet.manual-top-up')
            : redirect()->route('wallet.manual-top-up');

        return $redirect->with('success', 'Payment proof submitted. We will credit your wallet after admin verification.');
    }

    private function backToWallet(UserRole $role): RedirectResponse
    {
        if ($role === UserRole::Seller) {
            return redirect()->route('seller.wallet');
        }

        return redirect()->route('wallet.index');
    }
}
