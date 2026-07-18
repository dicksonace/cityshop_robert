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
        $sellerId = $request->user()->id;

        $disputes = Dispute::with(['order', 'buyer', 'orderItem.product.images'])
            ->where('seller_id', $sellerId)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $base = Dispute::where('seller_id', $sellerId);

        $counts = [
            'open' => (clone $base)->where('status', DisputeStatus::Open)->count(),
            'under_review' => (clone $base)->where('status', DisputeStatus::UnderReview)->count(),
            'resolved_buyer' => (clone $base)->where('status', DisputeStatus::ResolvedBuyer)->count(),
            'resolved_seller' => (clone $base)->where('status', DisputeStatus::ResolvedSeller)->count(),
            'cancelled' => (clone $base)->where('status', DisputeStatus::Cancelled)->count(),
            'all' => (clone $base)->count(),
        ];

        return Inertia::render('seller/disputes/index', [
            'disputes' => $disputes,
            'status' => $status,
            'counts' => $counts,
        ]);
    }
}
