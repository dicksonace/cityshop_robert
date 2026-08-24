<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\UserStatus;
use App\Services\StatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(StatusService::feed($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
            'body' => ['nullable', 'string', 'max:500'],
            'background_color' => ['nullable', 'string', 'max:16'],
        ]);

        $status = StatusService::post(
            $request->user(),
            [
                'body' => $request->input('body'),
                'background_color' => $request->input('background_color'),
            ],
            $request->file('image'),
        );

        return response()->json([
            'status' => StatusService::formatStatus($status, $request->user(), viewedIds: [(int) $status->id]),
        ], 201);
    }

    public function view(Request $request, UserStatus $status): JsonResponse
    {
        return response()->json([
            'status' => StatusService::markViewed($status, $request->user()),
        ]);
    }

    public function destroy(Request $request, UserStatus $status): JsonResponse
    {
        StatusService::destroy($status, $request->user());

        return response()->json(['ok' => true]);
    }
}
