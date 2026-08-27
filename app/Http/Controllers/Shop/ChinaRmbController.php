<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ChinaTransfer;
use App\Models\SellRmbTransfer;
use App\Services\ChinaTransferService;
use App\Services\SellRmbService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChinaRmbController extends Controller
{
    public function __construct(
        private ChinaTransferService $buyRmb,
        private SellRmbService $sellRmb,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isBuyer(), 403);

        $user = $request->user();
        $userId = $user->id;
        $wallet = WalletService::ensure($user);

        $buyTransfers = ChinaTransfer::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ChinaTransfer $item) => $this->buyRmb->transferPayload($item))
            ->values()
            ->all();

        $sellTransfers = SellRmbTransfer::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (SellRmbTransfer $item) => $this->sellRmb->transferPayload($item))
            ->values()
            ->all();

        return Inertia::render('shop/china-rmb/index', [
            'wallet' => $wallet->toFrontendArray(),
            'buy' => [
                'config' => $this->buyRmb->configPayload(),
                'transfers' => $buyTransfers,
            ],
            'sell' => [
                'config' => $this->sellRmb->configPayload(),
                'transfers' => $sellTransfers,
            ],
        ]);
    }
}
