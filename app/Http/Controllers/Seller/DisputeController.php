<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DisputeController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status', 'open');

        $disputes = Dispute::with(['order', 'buyer', 'orderItem.product.images'])
            ->where('seller_id', $request->user()->id)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('seller/disputes/index', [
            'disputes' => $disputes,
            'status' => $status,
        ]);
    }
}
