<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserLookupController extends Controller
{
    /**
     * Find a registered CityShop user by mobile number (exact match after normalizing).
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:30'],
        ]);

        $candidates = static::mobileCandidates($validated['mobile']);
        if ($candidates === []) {
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

    /**
     * @return list<string>
     */
    public static function mobileCandidates(string $raw): array
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return [];
        }

        $variants = [$digits, $raw];

        // Ghana local 0XXXXXXXXX ↔ 233XXXXXXXXX
        if (str_starts_with($digits, '233') && strlen($digits) >= 12) {
            $variants[] = '0'.substr($digits, 3);
            $variants[] = substr($digits, 3);
        } elseif (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            $variants[] = '233'.substr($digits, 1);
            $variants[] = substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $variants[] = '0'.$digits;
            $variants[] = '233'.$digits;
        }

        return array_values(array_unique(array_filter($variants)));
    }
}
