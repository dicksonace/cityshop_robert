<?php

namespace App\Http\Controllers\Shop;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\PaystackService;
use App\Services\WalletService;
use App\Services\WalletTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(private PaystackService $paystack) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isBuyer(), 403);

        $userId = $request->user()->id;
        $wallet = WalletService::ensure($request->user());

        $transactions = WalletTransaction::where('user_id', $userId)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('shop/wallet', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'paystackConfigured' => $this->paystack->isConfigured(),
            'paystackPublicKey' => config('services.paystack.public_key'),
        ]);
    }

    public function addFunds(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isBuyer(), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:5', 'max:50000'],
            'method' => ['required', 'in:momo,card'],
        ]);

        if (! $this->paystack->isConfigured()) {
            return back()->with('error', 'Online top-up is not available. Contact support.');
        }

        $amount = (float) $validated['amount'];
        $reference = 'TOP-'.strtoupper(uniqid());

        try {
            $data = $this->paystack->initializeTransaction(
                $request->user()->email,
                $amount,
                $reference,
                [
                    'type' => 'wallet_topup',
                    'user_id' => $request->user()->id,
                    'method' => $validated['method'],
                ],
                route('wallet.callback'),
            );

            return Inertia::location($data['authorization_url']);
        } catch (\Throwable $e) {
            Log::error('Wallet top-up init failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Could not start payment. Please try again.');
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isBuyer(), 403);

        $reference = $request->query('reference');

        if (! $reference) {
            return redirect()->route('wallet.index')->with('error', 'Invalid payment reference.');
        }

        try {
            $data = $this->paystack->verifyTransaction($reference);

            if ($data['status'] !== 'success') {
                return redirect()->route('wallet.index')->with('error', 'Payment was not successful.');
            }

            $metadata = $data['metadata'] ?? [];
            if (($metadata['type'] ?? '') !== 'wallet_topup') {
                return redirect()->route('wallet.index')->with('error', 'Invalid wallet top-up.');
            }

            if ((int) ($metadata['user_id'] ?? 0) !== $request->user()->id) {
                return redirect()->route('wallet.index')->with('error', 'Payment does not belong to your account.');
            }

            $amount = round(((int) ($data['amount'] ?? 0)) / 100, 2);
            $method = (string) ($metadata['method'] ?? 'momo');

            WalletService::creditFromVerifiedTopUp($request->user()->id, $amount, $reference, $method);

            return redirect()->route('wallet.index')
                ->with('success', 'GH₵'.number_format($amount, 2).' added to your wallet.');
        } catch (\Throwable $e) {
            Log::error('Wallet callback error', ['error' => $e->getMessage()]);

            return redirect()->route('wallet.index')->with('error', 'Payment verification failed.');
        }
    }

    public function withdraw(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isBuyer(), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10'],
            'momo_number' => ['required', 'string', 'max:20'],
            'account_name' => ['required', 'string', 'max:255'],
            'network' => ['required', 'in:mtn,telecel,airteltigo'],
        ]);

        $amount = (float) $validated['amount'];

        return DB::transaction(function () use ($request, $validated, $amount) {
            $wallet = Wallet::where('user_id', $request->user()->id)->lockForUpdate()->first()
                ?? WalletService::ensure($request->user());

            if ($amount > (float) $wallet->available_balance) {
                return back()->with('error', 'Insufficient available balance.');
            }

            $pending = Withdrawal::where('user_id', $request->user()->id)
                ->where('status', WithdrawalStatus::Pending)
                ->exists();

            if ($pending) {
                return back()->with('error', 'You already have a pending withdrawal request.');
            }

            $withdrawal = Withdrawal::create([
                'user_id' => $request->user()->id,
                'amount' => $amount,
                'momo_number' => $validated['momo_number'],
                'account_name' => $validated['account_name'],
                'network' => $validated['network'],
                'status' => WithdrawalStatus::Pending,
            ]);

            $wallet->decrement('available_balance', $amount);
            WalletTransactionService::recordWithdrawal($withdrawal);

            return back()->with('success', 'Withdrawal request submitted.');
        });
    }
}
