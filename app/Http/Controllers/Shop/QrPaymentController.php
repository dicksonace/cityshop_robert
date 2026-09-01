<?php

namespace App\Http\Controllers\Shop;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\KycService;
use App\Services\PaymentPinService;
use App\Services\QrPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QrPaymentController extends Controller
{
    public function receive(Request $request): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:1', 'max:50000'],
            'reason' => ['nullable', 'string', 'max:80'],
        ]);

        $amount = array_key_exists('amount', $validated) && $validated['amount'] !== null
            ? (float) $validated['amount']
            : null;
        $reason = isset($validated['reason']) ? trim((string) $validated['reason']) : null;
        if ($reason === '') {
            $reason = null;
        }

        $qr = QrPaymentService::receiveCode($user, $amount, $reason);
        $page = $user->isSeller() ? 'seller/wallet/qr-receive' : 'shop/wallet/qr-receive';

        return Inertia::render($page, [
            'qr' => $qr,
            'amount' => $amount,
            'reason' => $reason,
        ]);
    }

    public function pay(Request $request): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $page = $user->isSeller() ? 'seller/wallet/qr-pay' : 'shop/wallet/qr-pay';

        return Inertia::render($page, [
            'hasPaymentPin' => PaymentPinService::hasPin($user),
            'kyc' => KycService::payload($user, false),
        ]);
    }

    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payload' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $data = QrPaymentService::resolvePayload($validated['payload'], $request->user());
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json(['data' => $data]);
    }

    public function submitPay(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $validated = $request->validate([
            'payload' => ['required', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
            'note' => ['nullable', 'string', 'max:120'],
            'payment_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ]);

        PaymentPinService::assertValidForAction($user, $validated['payment_pin']);

        try {
            $result = QrPaymentService::pay(
                $user,
                $validated['payload'],
                (float) $validated['amount'],
                $validated['note'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $recipient = $result['recipient']['name'] ?? 'recipient';
        $amountLabel = number_format((float) $validated['amount'], 2);

        return redirect()
            ->route($user->isSeller() ? 'seller.wallet' : 'wallet.index')
            ->with('success', "Sent GH₵{$amountLabel} to {$recipient}.");
    }
}
