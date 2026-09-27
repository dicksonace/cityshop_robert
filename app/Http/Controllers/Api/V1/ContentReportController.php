<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentReportTarget;
use App\Enums\SellerReportReason;
use App\Enums\SellerReportStatus;
use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => ['required', Rule::enum(ContentReportTarget::class)],
            'target_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::enum(SellerReportReason::class)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $target = ContentReportTarget::from($validated['target_type']);
        $targetId = (int) $validated['target_id'];
        $ownerId = $this->ownerId($user, $target, $targetId);

        if ($ownerId === null) {
            return response()->json(['message' => 'That content cannot be reported.'], 422);
        }

        if ($ownerId === $user->id) {
            return response()->json(['message' => 'You cannot report your own content.'], 422);
        }

        $alreadyOpen = ContentReport::query()
            ->where('reporter_id', $user->id)
            ->where('target_type', $target)
            ->where('target_id', $targetId)
            ->whereIn('status', [SellerReportStatus::Open, SellerReportStatus::Reviewing])
            ->exists();

        if ($alreadyOpen) {
            return response()->json([
                'message' => 'You already reported this. Our team is reviewing it.',
            ], 422);
        }

        ContentReport::query()->create([
            'reporter_id' => $user->id,
            'target_type' => $target,
            'target_id' => $targetId,
            'owner_id' => $ownerId,
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
            'status' => SellerReportStatus::Open,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Report submitted. We review reports within 24 hours. You can also block this person from the chat.',
        ]);
    }

    private function ownerId(User $reporter, ContentReportTarget $target, int $targetId): ?int
    {
        return match ($target) {
            ContentReportTarget::Product => $this->intOrNull(Product::query()->whereKey($targetId)->value('seller_id')),
            ContentReportTarget::Status => $this->intOrNull(UserStatus::query()->whereKey($targetId)->value('user_id')),
            ContentReportTarget::Message => $this->messageOwnerId($reporter, $targetId),
        };
    }

    private function messageOwnerId(User $reporter, int $messageId): ?int
    {
        $message = Message::query()->with('conversation')->find($messageId);
        if (! $message || ! $message->conversation || ! $message->conversation->involves($reporter)) {
            return null;
        }

        return $message->sender_id ? (int) $message->sender_id : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
