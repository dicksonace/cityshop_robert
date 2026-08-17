<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;

class ContactMessageController extends Controller
{
    public function index(): JsonResponse
    {
        $messages = ContactMessage::with('user:id,name,email,mobile')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $messages->getCollection()->map(fn (ContactMessage $message) => [
                'id' => $message->id,
                'name' => $message->name,
                'email' => $message->email,
                'mobile' => $message->phone,
                'subject' => $message->subject,
                'body' => $message->message,
                'is_read' => (bool) $message->is_read,
                'created_at' => $message->created_at?->toIso8601String(),
                'user' => $message->user ? [
                    'id' => $message->user->id,
                    'name' => $message->user->name,
                ] : null,
            ])->values(),
            'meta' => AdminJson::meta($messages),
        ]);
    }

    public function markRead(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->update(['is_read' => true]);

        return response()->json(['message' => 'Message marked as read.']);
    }
}
