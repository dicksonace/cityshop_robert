<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverAdminBuyerAnnouncement;
use App\Jobs\DeliverAdminSellerAnnouncement;
use App\Models\AdminAnnouncement;
use App\Models\AdminBuyerAnnouncement;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function sellerIndex(): JsonResponse
    {
        $page = AdminAnnouncement::with('admin:id,name')->latest()->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (AdminAnnouncement $a) => $this->sellerSerialize($a))->values(),
            'meta' => AdminJson::meta($page),
        ]);
    }

    public function sellerRecipients(): JsonResponse
    {
        $sellers = SellerProfile::query()
            ->with('user:id,name,email,mobile')
            ->where('status', SellerStatus::Approved)
            ->orderBy('store_name')
            ->get()
            ->map(fn (SellerProfile $profile) => [
                'id' => $profile->id,
                'name' => $profile->displayName(),
                'email' => $profile->user?->email,
                'mobile' => $profile->user?->mobile,
            ]);

        return response()->json(['data' => $sellers]);
    }

    public function sellerStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audience' => ['required', 'in:one,selected,all'],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'seller_ids' => ['nullable', 'array'],
            'seller_ids.*' => ['integer', 'exists:seller_profiles,id'],
            'send_email' => ['boolean'],
        ]);

        $sellerIds = collect($validated['seller_ids'] ?? [])->unique()->values()->all();

        if (in_array($validated['audience'], ['one', 'selected'], true) && $sellerIds === []) {
            return response()->json(['message' => 'Select at least one seller.', 'errors' => ['seller_ids' => ['Select at least one seller.']]], 422);
        }

        $announcement = AdminAnnouncement::create([
            'admin_id' => $request->user()->id,
            'audience' => $validated['audience'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'seller_profile_ids' => $validated['audience'] === 'all' ? null : $sellerIds,
            'send_email' => (bool) ($validated['send_email'] ?? false),
            'recipients_count' => 0,
        ]);

        DeliverAdminSellerAnnouncement::dispatchSync($announcement);

        return response()->json(['message' => 'Message sent to sellers.', 'data' => $this->sellerSerialize($announcement->fresh('admin'))], 201);
    }

    public function buyerIndex(): JsonResponse
    {
        $page = AdminBuyerAnnouncement::with('admin:id,name')->latest()->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (AdminBuyerAnnouncement $a) => [
                'id' => $a->id,
                'audience' => $a->audience,
                'title' => $a->title,
                'body' => $a->body,
                'send_email' => $a->send_email,
                'recipients_count' => $a->recipients_count,
                'admin' => $a->admin?->only(['id', 'name']),
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values(),
            'meta' => AdminJson::meta($page),
        ]);
    }

    public function buyerRecipients(): JsonResponse
    {
        $buyers = User::query()
            ->where('role', UserRole::Buyer)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'email', 'mobile']);

        return response()->json(['data' => $buyers]);
    }

    public function buyerStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audience' => ['required', 'in:one,selected,all'],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'buyer_ids' => ['nullable', 'array'],
            'buyer_ids.*' => ['integer', 'exists:users,id'],
            'send_email' => ['boolean'],
        ]);

        $buyerIds = collect($validated['buyer_ids'] ?? [])->unique()->values()->all();

        if (in_array($validated['audience'], ['one', 'selected'], true) && $buyerIds === []) {
            return response()->json(['message' => 'Select at least one buyer.', 'errors' => ['buyer_ids' => ['Select at least one buyer.']]], 422);
        }

        $announcement = AdminBuyerAnnouncement::create([
            'admin_id' => $request->user()->id,
            'audience' => $validated['audience'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'buyer_user_ids' => $validated['audience'] === 'all' ? null : $buyerIds,
            'send_email' => (bool) ($validated['send_email'] ?? false),
            'recipients_count' => 0,
        ]);

        DeliverAdminBuyerAnnouncement::dispatchSync($announcement);

        return response()->json(['message' => 'Message sent to buyers.'], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function sellerSerialize(AdminAnnouncement $a): array
    {
        return [
            'id' => $a->id,
            'audience' => $a->audience,
            'title' => $a->title,
            'body' => $a->body,
            'send_email' => $a->send_email,
            'recipients_count' => $a->recipients_count,
            'admin' => $a->admin?->only(['id', 'name']),
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }
}
