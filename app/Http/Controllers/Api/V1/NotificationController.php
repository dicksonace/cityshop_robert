<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Order;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = AppNotification::where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => ChatService::unreadNotificationCount($request->user()),
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
            'unread_count' => ChatService::unreadNotificationCount($request->user()),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }

    public function counts(Request $request): JsonResponse
    {
        $buyerId = $request->user()->id;

        return response()->json([
            'unread_messages' => ChatService::unreadMessageCount($request->user()),
            'unread_notifications' => ChatService::unreadNotificationCount($request->user()),
            'total_orders' => Order::where('buyer_id', $buyerId)->count(),
            'active_orders' => Order::where('buyer_id', $buyerId)
                ->whereNotIn('status', [
                    OrderStatus::Delivered,
                    OrderStatus::Cancelled,
                    OrderStatus::Refunded,
                ])
                ->count(),
        ]);
    }
}
