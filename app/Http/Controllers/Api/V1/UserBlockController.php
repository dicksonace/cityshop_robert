<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\UserBlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserBlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $blocks = UserBlock::query()
            ->with('blocked:id,name,mobile,avatar,role')
            ->where('blocker_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(function (UserBlock $block) {
                $user = $block->blocked;
                $avatar = $user?->displayAvatarPath();

                return [
                    'id' => $block->id,
                    'blocked_at' => $block->created_at?->toIso8601String(),
                    'user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'mobile' => $user->mobile,
                        'role' => $user->role?->value,
                        'avatar' => $avatar
                            ? (str_starts_with($avatar, 'http')
                                ? $avatar
                                : Storage::disk('public')->url($avatar))
                            : null,
                    ] : null,
                ];
            });

        return response()->json(['data' => $blocks]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $blocked = User::findOrFail($validated['user_id']);

        try {
            UserBlockService::block($request->user(), $blocked);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'User blocked. They can no longer message you.',
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        UserBlockService::unblock($request->user(), $user);

        return response()->json([
            'ok' => true,
            'message' => 'User unblocked.',
        ]);
    }
}
