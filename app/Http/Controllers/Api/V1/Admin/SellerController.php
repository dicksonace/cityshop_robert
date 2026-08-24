<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSellerInformationRequest;
use App\Models\SellerPaymentMethod;
use App\Models\SellerProfile;
use App\Notifications\SellerApprovedNotification;
use App\Notifications\SellerRejectedNotification;
use App\Services\SellerAccountService;
use App\Services\SellerActivationService;
use App\Services\SellerPaymentMethodSecurityService;
use App\Services\SellerRegistrationInviteService;
use App\Services\StoreCustomizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SellerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status', 'pending')->toString();
        $search = $request->string('search')->trim()->toString();

        $sellers = SellerProfile::with('user:id,name,email,mobile')
            ->whereHas('user')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('store_name', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $sellers->getCollection()->map(fn (SellerProfile $seller) => $this->serialize($seller))->values(),
            'meta' => $this->meta($sellers),
            'status' => $status,
        ]);
    }

    public function show(SellerProfile $seller): JsonResponse
    {
        $seller->load('user');

        return response()->json(['data' => $this->serialize($seller, detailed: true)]);
    }

    public function approve(Request $request, SellerProfile $seller, StoreCustomizationService $customizations): JsonResponse
    {
        $seller->update([
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'rejection_reason' => null,
        ]);

        $customization = $customizations->forProfile($seller);
        $customization->update(['setup_completed_at' => null]);
        $seller->user->notify(new SellerApprovedNotification($seller));

        return response()->json([
            'message' => 'Seller approved.',
            'data' => $this->serialize($seller->fresh('user'), detailed: true),
        ]);
    }

    public function reject(Request $request, SellerProfile $seller): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $seller->update([
            'status' => SellerStatus::Rejected,
            'rejection_reason' => $validated['rejection_reason'],
        ]);
        $seller->user->notify(new SellerRejectedNotification($validated['rejection_reason']));

        return response()->json([
            'message' => 'Seller application rejected.',
            'data' => $this->serialize($seller->fresh('user'), detailed: true),
        ]);
    }

    public function block(Request $request, SellerProfile $seller, SellerAccountService $accounts): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $accounts->block($seller, $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Seller blocked. Their products are hidden.',
            'data' => $this->serialize($seller->fresh('user'), detailed: true),
        ]);
    }

    public function unblock(Request $request, SellerProfile $seller, SellerAccountService $accounts): JsonResponse
    {
        try {
            $accounts->unblock($seller, $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Seller unblocked.',
            'data' => $this->serialize($seller->fresh('user'), detailed: true),
        ]);
    }

    public function promptActivation(Request $request, SellerProfile $seller, SellerActivationService $activation): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
        ]);
        $activation->prompt($seller, (float) $validated['amount']);

        return response()->json([
            'message' => 'Seller prompted. Products are hidden until they pay. SMS and in-app notification sent.',
            'data' => $this->serialize($seller->fresh('user'), detailed: true),
        ]);
    }

    public function waiveActivation(Request $request, SellerProfile $seller, SellerActivationService $activation): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:1', 'max:50000'],
        ]);
        $activation->waiveForYear($seller, isset($validated['amount']) ? (float) $validated['amount'] : null);

        return response()->json([
            'message' => 'Store activated for 1 year without charging the wallet.',
            'data' => $this->serialize($seller->fresh('user'), detailed: true),
        ]);
    }

    public function endActivation(SellerProfile $seller, SellerActivationService $activation): JsonResponse
    {
        $activation->endNow($seller);

        return response()->json([
            'message' => 'Activation ended. Products stay hidden until they pay.',
            'data' => $this->serialize($seller->fresh('user'), detailed: true),
        ]);
    }

    public function updateProfile(UpdateSellerInformationRequest $request, SellerProfile $seller): JsonResponse
    {
        $seller->loadMissing('user');
        $validated = $request->validated();

        $seller->user->updateAccountDetails(
            $validated['name'],
            $validated['email'],
            $validated['mobile'],
            [
                'ghana_card_number' => $validated['ghana_card_number'] ?: null,
                'region' => $validated['region'] ?: null,
                'city' => $validated['city'] ?: null,
                'residential_address' => $validated['residential_address'] ?: null,
            ],
        );

        $storeName = $validated['store_name'];
        $registered = (bool) $validated['is_business_registered'];

        $seller->update([
            'store_name' => $storeName,
            'is_business_registered' => $registered,
            'business_name' => $registered ? ($validated['business_name'] ?: $storeName) : $seller->business_name,
            'business_registration_number' => $registered ? ($validated['business_registration_number'] ?: null) : null,
            'accept_marketplace_payments' => (bool) $validated['accept_marketplace_payments'],
            'accept_direct_payments' => (bool) $validated['accept_direct_payments'],
        ]);

        return response()->json([
            'message' => 'Seller information updated.',
            'data' => $this->serialize($seller->fresh('user'), detailed: true),
        ]);
    }

    public function destroy(Request $request, SellerProfile $seller, SellerAccountService $accounts): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'confirm_store_name' => ['required', 'string'],
        ]);

        $expectedName = $seller->business_name ?? $seller->store_name ?? '';
        if (strcasecmp(trim($validated['confirm_store_name']), trim($expectedName)) !== 0) {
            return response()->json(['message' => 'Store name confirmation did not match. Account was not deleted.'], 422);
        }

        try {
            $accounts->delete($seller, $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Seller account deleted. Their listings were removed from the marketplace.']);
    }

    public function disablePaymentMethod(
        Request $request,
        SellerProfile $seller,
        SellerPaymentMethod $method,
        SellerPaymentMethodSecurityService $security,
    ): JsonResponse {
        abort_unless($method->seller_profile_id === $seller->id, 404);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        try {
            $security->disable($method, $request->user(), $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Payment method disabled.']);
    }

    public function enablePaymentMethod(
        Request $request,
        SellerProfile $seller,
        SellerPaymentMethod $method,
        SellerPaymentMethodSecurityService $security,
    ): JsonResponse {
        abort_unless($method->seller_profile_id === $seller->id, 404);
        try {
            $security->enable($method, $request->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Payment method enabled again.']);
    }

    public function unlockPaymentMethods(
        SellerProfile $seller,
        SellerPaymentMethodSecurityService $security,
    ): JsonResponse {
        if (! $seller->paymentMethodsAreLocked()) {
            return response()->json(['message' => 'Payment method setup is not locked for this seller.'], 422);
        }
        $security->unlockPaymentSetup($seller);

        return response()->json(['message' => 'Seller can add payment methods again.']);
    }

    public function resendInvite(
        Request $request,
        SellerProfile $seller,
        SellerRegistrationInviteService $invites,
    ): JsonResponse {
        $seller->load('user');
        $invite = $invites->create(
            $request->user(),
            $seller->user->email,
            $seller->user->name,
            'Resent after application review.',
            $seller,
        );

        return response()->json([
            'message' => 'A new seller registration link has been created.',
            'registration_url' => $invite->registrationUrl(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SellerProfile $seller, bool $detailed = false): array
    {
        $payload = [
            'id' => $seller->id,
            'store_name' => $seller->displayName(),
            'slug' => $seller->slug,
            'status' => $seller->status?->value,
            'shop_photo' => $this->publicUrl($seller->shop_photo),
            'created_at' => $seller->created_at?->toIso8601String(),
            'user' => $seller->user ? [
                'id' => $seller->user->id,
                'name' => $seller->user->name,
                'email' => $seller->user->email,
                'mobile' => $seller->user->mobile,
            ] : null,
        ];

        if ($detailed) {
            $payload['rejection_reason'] = $seller->rejection_reason;
            $payload['activation'] = $seller->activationPayload();
            $payload['store_name_raw'] = $seller->store_name;
            $payload['is_business_registered'] = (bool) $seller->is_business_registered;
            $payload['business_name'] = $seller->business_name;
            $payload['business_registration_number'] = $seller->business_registration_number;
            $payload['accept_marketplace_payments'] = (bool) $seller->accept_marketplace_payments;
            $payload['accept_direct_payments'] = (bool) $seller->accept_direct_payments;
            $payload['payment_methods_locked'] = $seller->paymentMethodsAreLocked();
            $payload['id_card_front'] = $this->publicUrl($seller->id_card_front);
            $payload['id_card_back'] = $this->publicUrl($seller->id_card_back);
            $payload['form_a'] = $this->publicUrl($seller->form_a);
            $payload['form_b'] = $this->publicUrl($seller->form_b);
            $payload['business_certificate'] = $this->publicUrl($seller->business_certificate);
            $payload['selfie_with_id'] = $this->publicUrl($seller->selfie_with_id);
            $payload['user'] = $seller->user ? [
                'id' => $seller->user->id,
                'name' => $seller->user->name,
                'email' => $seller->user->email,
                'mobile' => $seller->user->mobile,
                'ghana_card_number' => $seller->user->ghana_card_number,
                'region' => $seller->user->region,
                'city' => $seller->user->city,
                'residential_address' => $seller->user->residential_address,
            ] : null;
            $payload['payment_methods'] = $seller->paymentMethods()
                ->withTrashed()
                ->latest('id')
                ->get()
                ->map(fn (SellerPaymentMethod $method) => [
                    'id' => $method->id,
                    'label' => $method->displayLabel(),
                    'type' => $method->type?->value,
                    'is_disabled' => $method->isDisabled(),
                    'disabled_reason' => $method->disabled_reason,
                ])
                ->values();
        }

        return $payload;
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, SellerProfile>  $page
     * @return array<string, int>
     */
    private function meta($page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : Storage::disk('public')->url($path);
    }
}
