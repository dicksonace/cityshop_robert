<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->trim()->toString();

        $transactions = WalletTransaction::query()
            ->with(['user:id,name,email,mobile,role'])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('user', function ($user) use ($search) {
                    $user->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                })->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->when($type !== '' && $type !== 'all', function ($query) use ($type) {
                $query->where('type', $type);
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(function (WalletTransaction $tx) {
                return [
                    'id' => $tx->id,
                    'type' => $tx->type?->value,
                    'type_label' => $tx->type?->label(),
                    'amount' => (float) $tx->amount,
                    'description' => $tx->description,
                    'reference' => $tx->reference,
                    'created_at' => $tx->created_at?->toIso8601String(),
                    'user' => $tx->user ? [
                        'id' => $tx->user->id,
                        'name' => $tx->user->name,
                        'email' => $tx->user->email,
                        'mobile' => $tx->user->mobile,
                        'role' => $tx->user->role?->value,
                    ] : null,
                ];
            });

        return Inertia::render('admin/transactions/index', [
            'transactions' => $transactions,
            'search' => $search !== '' ? $search : null,
            'type' => $type !== '' ? $type : 'all',
            'types' => [
                'all' => 'All types',
                'transfer_out' => 'Money Sent',
                'transfer_in' => 'Money Received',
                'fund_added' => 'Funds Credited',
                'fund_removed' => 'Funds Debited',
                'order_payment' => 'Order Payment',
                'order_refund' => 'Order Refund',
                'withdrawal' => 'Withdrawal Request',
                'withdrawal_completed' => 'Payout Sent',
                'sale_pending' => 'Sale (Pending)',
                'sale_released' => 'Funds Released',
            ],
        ]);
    }
}
