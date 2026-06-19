<?php

namespace App\Http\Controllers\Shop;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Notifications\DisputeOpenedNotification;
use App\Models\User;
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

        if (Dispute::where('order_item_id', $item->id)->exists()) {
            return back()->with('error', 'A dispute already exists for this item.');
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

        return back()->with('success', 'Dispute submitted. Our team will review it shortly.');
    }
}
