<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        // Name / email / mobile stay locked on the app so a stolen session
        // cannot quietly rewrite account identity. Avatar + password stay editable.
        return response()->json([
            'message' => 'Name, email, and mobile cannot be changed in the app. Contact CityShop support if you need an update.',
        ], 403);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'avatar.required' => 'Please choose a profile picture.',
            'avatar.image' => 'Profile picture must be an image.',
            'avatar.max' => 'Profile picture must be 5MB or smaller.',
        ]);

        $user = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->forceFill(['avatar' => $path])->save();

        return response()->json([
            'message' => 'Profile picture updated.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function destroyAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->forceFill(['avatar' => null])->save();
        }

        return response()->json([
            'message' => 'Profile picture removed.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return response()->json(['message' => 'Password updated.']);
    }
}
