<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MfaController extends Controller
{
    public function __construct(private MfaService $mfa) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['mfa' => $this->mfa->status($request->user())]);
    }

    public function sendEmail(Request $request): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        $this->mfa->assertPassword($user, $validated['password']);
        $this->mfa->sendEmailCode($user, 'setup');

        return response()->json([
            'message' => $this->mfa->sentMessage($user),
            'email_hint' => $this->mfa->emailHint($user),
        ]);
    }

    public function confirmEmail(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $user = $request->user();
        $this->mfa->verifyEmailCode($user, $validated['code'], 'setup');
        $this->mfa->enableEmail($user);

        return response()->json([
            'message' => 'Email sign-in codes are on.',
            'mfa' => $this->mfa->status($user->fresh()),
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function disableEmail(Request $request): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        $this->mfa->assertPassword($user, $validated['password']);
        $this->mfa->disableEmail($user);

        return response()->json([
            'message' => 'Email sign-in codes are off.',
            'mfa' => $this->mfa->status($user->fresh()),
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function startTotp(Request $request): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        $this->mfa->assertPassword($user, $validated['password']);

        return response()->json($this->mfa->startTotp($user));
    }

    public function confirmTotp(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $user = $request->user();
        $this->mfa->confirmTotp($user, $validated['code']);

        return response()->json([
            'message' => 'Authenticator app is on.',
            'mfa' => $this->mfa->status($user->fresh()),
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function disableTotp(Request $request): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        $this->mfa->assertPassword($user, $validated['password']);
        $this->mfa->disableTotp($user);

        return response()->json([
            'message' => 'Authenticator app is off.',
            'mfa' => $this->mfa->status($user->fresh()),
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function verifyLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mfa_token' => ['required', 'string'],
            'method' => ['required', 'in:email,totp'],
            'code' => ['required', 'string', 'max:12'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $challenge = $this->mfa->parseApiChallenge($validated['mfa_token']);
        $user = User::query()->find($challenge['uid']);
        if (! $user) {
            return response()->json(['message' => 'This sign-in check expired. Log in again.'], 422);
        }

        $this->mfa->verifyLogin($user, $validated['method'], $validated['code']);

        $token = $user->createToken($validated['device_name'] ?? $challenge['device'] ?? 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function resendLoginEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mfa_token' => ['required', 'string'],
        ]);
        $challenge = $this->mfa->parseApiChallenge($validated['mfa_token']);
        $user = User::query()->find($challenge['uid']);
        if (! $user) {
            return response()->json(['message' => 'This sign-in check expired. Log in again.'], 422);
        }

        $this->mfa->sendEmailCode($user);

        return response()->json([
            'message' => $this->mfa->sentMessage($user),
        ]);
    }
}
