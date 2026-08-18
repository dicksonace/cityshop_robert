<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Notifications\KycDecisionNotification;
use App\Services\AppNotificationService;
use App\Services\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status', 'pending')->toString();
        $search = $request->string('search')->trim()->toString();

        $items = KycVerification::query()
            ->with('user:id,name,email,mobile,role')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('ghana_card_number', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $items->getCollection()->map(fn (KycVerification $kyc) => $this->serialize($kyc))->values(),
            'meta' => AdminJson::meta($items),
            'status' => $status,
        ]);
    }

    public function show(KycVerification $kyc): JsonResponse
    {
        $kyc->load('user:id,name,email,mobile,role');

        return response()->json(['data' => $this->serialize($kyc, detailed: true)]);
    }

    public function approve(Request $request, KycVerification $kyc): JsonResponse
    {
        $kyc = KycService::approve($kyc, $request->user());
        $this->notifyUser($kyc);

        return response()->json([
            'message' => 'Ghana Card approved. This user can now store money in their wallet.',
            'data' => $this->serialize($kyc, detailed: true),
        ]);
    }

    public function reject(Request $request, KycVerification $kyc): JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:4', 'max:500'],
        ]);
        $kyc = KycService::reject($kyc, $request->user(), $validated['admin_notes']);
        $this->notifyUser($kyc);

        return response()->json([
            'message' => 'Ghana Card rejected.',
            'data' => $this->serialize($kyc, detailed: true),
        ]);
    }

    public function requestChanges(Request $request, KycVerification $kyc): JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:4', 'max:500'],
        ]);
        $kyc = KycService::requestChanges($kyc, $request->user(), $validated['admin_notes']);
        $this->notifyUser($kyc);

        return response()->json([
            'message' => 'Asked the user to improve their Ghana Card photos.',
            'data' => $this->serialize($kyc, detailed: true),
        ]);
    }

    private function notifyUser(KycVerification $kyc): void
    {
        $user = $kyc->user;
        if (! $user) {
            return;
        }

        try {
            $user->notify(new KycDecisionNotification($kyc));
        } catch (\Throwable $e) {
            report($e);
        }

        $title = match ($kyc->status) {
            KycStatus::Approved => 'Ghana Card verified',
            KycStatus::NeedsImprovement => 'Improve your Ghana Card',
            default => 'Ghana Card not approved',
        };
        $body = match ($kyc->status) {
            KycStatus::Approved => 'You can now recharge and store money in your wallet.',
            KycStatus::NeedsImprovement => $kyc->admin_notes ?: 'Please submit clearer Ghana Card photos.',
            default => $kyc->admin_notes ?: 'Your Ghana Card was not approved.',
        };

        AppNotificationService::send(
            $user,
            'kyc_'.$kyc->status->value,
            $title,
            $body,
            ['kyc_id' => $kyc->id, 'status' => $kyc->status->value],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(KycVerification $kyc, bool $detailed = false): array
    {
        $user = $kyc->user;
        $payload = [
            'id' => $kyc->id,
            'status' => $kyc->status?->value,
            'status_label' => $kyc->status?->label(),
            'ghana_card_number' => $kyc->ghana_card_number,
            'full_name' => $kyc->full_name,
            'admin_notes' => $kyc->admin_notes,
            'submitted_at' => $kyc->submitted_at?->toIso8601String() ?? $kyc->created_at?->toIso8601String(),
            'reviewed_at' => $kyc->reviewed_at?->toIso8601String(),
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'role' => $user->role?->value,
            ] : null,
        ];

        if ($detailed) {
            $payload['front_url'] = $kyc->publicUrl($kyc->front_path);
            $payload['back_url'] = $kyc->publicUrl($kyc->back_path);
            $payload['selfie_url'] = $kyc->publicUrl($kyc->selfie_path);
        }

        return $payload;
    }
}
