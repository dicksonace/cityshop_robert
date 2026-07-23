<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Enums\WalletTopUpStatus;
use App\Http\Controllers\Controller;
use App\Models\WalletTopUpRequest;
use App\Services\PlatformSettings;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $wallet = WalletService::ensure($user);

        return response()->json([
            'data' => [
                'available_balance' => (float) $wallet->available_balance,
                'pending_balance' => (float) $wallet->pending_balance,
                'total_earnings' => (float) $wallet->total_earnings,
                'withdrawn_amount' => (float) $wallet->withdrawn_amount,
            ],
        ]);
    }

    public function manualFunding(): JsonResponse
    {
        $settings = PlatformSettings::manualFundingAccounts();

        return response()->json([
            'enabled' => $settings['enabled'],
            'instructions' => $settings['instructions'],
            'accounts' => $settings['accounts'],
        ]);
    }

    public function manualTopUp(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $settings = PlatformSettings::manualFundingAccounts();

        if (! $settings['enabled'] || count($settings['accounts']) === 0) {
            return response()->json(['message' => 'Manual top-up is not available right now.'], 422);
        }

        if (WalletTopUpRequest::where('user_id', $user->id)->where('status', WalletTopUpStatus::Pending)->exists()) {
            return response()->json(['message' => 'You already have a pending manual top-up.'], 422);
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

        return response()->json([
            'message' => 'Payment proof submitted for verification.',
            'data' => [
                'id' => $topUp->id,
                'amount' => (float) $topUp->amount,
                'payment_reference' => $topUp->payment_reference,
                'network' => $topUp->network,
                'status' => $topUp->status->value,
            ],
        ], 201);
    }
}
