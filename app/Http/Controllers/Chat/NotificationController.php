<?php

namespace App\Http\Controllers\Chat;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $notifications = AppNotification::where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['notifications' => $notifications]);
        }

        return Inertia::render('chat/notifications', [
            'notifications' => $notifications,
            'layout' => $request->user()->role === UserRole::Seller ? 'seller' : 'shop',
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse|RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json(['ok' => true]);
    }

    public function counts(Request $request): JsonResponse
    {
        return response()->json([
            'unread_messages' => ChatService::unreadMessageCount($request->user()),
            'unread_notifications' => ChatService::unreadNotificationCount($request->user()),
        ]);
    }
}
