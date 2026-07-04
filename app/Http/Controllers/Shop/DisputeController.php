<?php

namespace App\Http\Controllers\Shop;

use App\Enums\DisputeStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\DisputeOpenedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class DisputeController extends Controller
{
    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        $validated = $request->validate([
            'order_item_id' => ['required', 'exists:order_items,id'],
            'reason' => ['required', 'in:wrong_item,damaged_item,not_delivered,other'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $item = OrderItem::where('id', $validated['order_item_id'])
            ->where('order_id', $order->id)
            ->firstOrFail();

        if (! in_array($item->status->value, ['shipped', 'delivered'], true)) {
            return back()->with('error', 'Disputes can only be opened for items that are out for delivery or delivered.');
        }

        if (Dispute::where('order_item_id', $item->id)->whereNotIn('status', [DisputeStatus::Cancelled])->exists()) {
            return back()->with('error', 'This item already has a refund or dispute on record.');
        }

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'buyer_id' => $request->user()->id,
            'seller_id' => $item->seller_id,
            'reason' => $validated['reason'],
            'description' => $validated['description'],
            'status' => DisputeStatus::Open,
        ]);

        $dispute->load('order');

        $item->seller->notify(new DisputeOpenedNotification($dispute));
        $request->user()->notify(new DisputeOpenedNotification($dispute));

        $admins = User::where('role', UserRole::Admin)->get();
        Notification::send($admins, new DisputeOpenedNotification($dispute));

        return back()->with('success', 'Refund request submitted. Admin will review before any refund is issued.');
    }

    public function cancel(Request $request, Dispute $dispute): RedirectResponse
    {
        abort_unless($dispute->buyer_id === $request->user()->id, 403);

        if (! in_array($dispute->status, [DisputeStatus::Open, DisputeStatus::UnderReview], true)) {
            return back()->with('error', 'This refund request can no longer be cancelled.');
        }

        $dispute->update([
            'status' => DisputeStatus::Cancelled,
            'resolution_notes' => 'Cancelled by buyer.',
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Refund request cancelled.');
    }
}
