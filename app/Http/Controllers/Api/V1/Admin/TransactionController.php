<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Services\WalletTransactionService;
use App\Services\WithdrawalPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->trim()->toString();

        $transactions = WalletTransaction::query()
            ->with([
                'user:id,name,email,mobile,role',
                'withdrawal:id,status,momo_number,fee,network,payout_channel,junipay_trans_id,junipay_reference,junipay_status,updated_at',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('user', function ($user) use ($search) {
                        $user->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    })->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($type !== '' && $type !== 'all', fn ($query) => $query->where('type', $type))
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        app(WithdrawalPayoutService::class)->refreshJunipayOnTransactions($transactions->getCollection());

        return response()->json([
            'data' => $transactions->getCollection()->map(fn (WalletTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type?->value,
                'type_label' => WalletTransactionService::displayTypeLabel($tx),
                'amount' => (float) $tx->amount,
                'description' => WalletTransactionService::displayDescription($tx),
                'reference' => $tx->reference,
                'created_at' => $tx->created_at?->toIso8601String(),
                'user' => $tx->user ? [
                    'id' => $tx->user->id,
                    'name' => $tx->user->name,
                    'mobile' => $tx->user->mobile,
                    'role' => $tx->user->role?->value,
                ] : null,
            ])->values(),
            'meta' => AdminJson::meta($transactions),
        ]);
    }
}
