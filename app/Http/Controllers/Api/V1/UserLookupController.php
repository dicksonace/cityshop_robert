<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\GhanaMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserLookupController extends Controller
{
    /**
     * Find a registered CityShop user by mobile number (0… / 233… treated the same).
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:30'],
        ]);

        $candidates = GhanaMobile::variants($validated['mobile']);
        if ($candidates === [] || GhanaMobile::to233($validated['mobile']) === null) {
            return response()->json(['message' => 'Enter a valid mobile number.'], 422);
        }

        $user = User::query()
            ->whereIn('mobile', $candidates)
            ->where('role', '!=', UserRole::Admin)
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'No CityShop account found with that mobile number.',
            ], 404);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'That is your own number.'], 422);
        }

        $avatar = $user->displayAvatarPath();
        $avatarUrl = null;
        if (is_string($avatar) && trim($avatar) !== '') {
            $avatarUrl = str_starts_with($avatar, 'http')
                ? $avatar
                : Storage::disk('public')->url($avatar);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->mobile,
                'role' => $user->role?->value,
                'avatar' => $avatarUrl,
                'city' => $user->city,
                'region' => $user->region,
            ],
        ]);
    }
}
