<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerPaymentMethod;
use App\Models\SellerProfile;
use App\Notifications\SellerApprovedNotification;
use App\Notifications\SellerRejectedNotification;
use App\Services\SellerAccountService;
use App\Services\SellerPaymentMethodSecurityService;
use App\Services\SellerRegistrationInviteService;
use App\Services\StoreCustomizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status', 'pending');
        $search = $request->string('search')->trim()->toString();

        $sellers = SellerProfile::with('user')
            ->whereHas('user')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('store_name', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sellers/index', [
            'sellers' => $sellers,
            'status' => $status,
            'search' => $search !== '' ? $search : null,
        ]);
    }

    public function show(SellerProfile $seller): Response
    {
        $seller->load([
            'user',
            'paymentMethods' => fn ($q) => $q->withTrashed()->latest('id'),
            'paymentMethodsLockedBy:id,name',
        ]);

        return Inertia::render('admin/sellers/show', [
            'seller' => $seller,
            'paymentMethods' => $seller->paymentMethods->map(fn (SellerPaymentMethod $method) => [
                'id' => $method->id,
                'type' => $method->type->value,
                'label' => $method->displayLabel(),
                'account_name' => $method->account_name,
                'account_number' => $method->account_number,
                'network' => $method->network,
                'bank_name' => $method->bank_name,
                'instructions' => $method->instructions,
                'is_active' => $method->is_active,
                'is_default' => $method->is_default,
                'is_disabled' => $method->isDisabled(),
                'disabled_reason' => $method->disabled_reason,
                'disabled_at' => $method->disabled_at?->toIso8601String(),
                'deleted_at' => $method->deleted_at?->toIso8601String(),
            ]),
            'paymentMethodsLocked' => $seller->paymentMethodsAreLocked(),
            'paymentMethodsLockReason' => $seller->payment_methods_lock_reason,
            'paymentMethodsLockedBy' => $seller->paymentMethodsLockedBy?->only(['id', 'name']),
            'paymentMethodsLockedAt' => $seller->payment_methods_locked_at?->toIso8601String(),
        ]);
    }

    public function approve(Request $request, SellerProfile $seller, StoreCustomizationService $customizations): RedirectResponse
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

        return back()->with('success', 'Seller approved successfully.');
    }

    public function reject(
        Request $request,
        SellerProfile $seller,
        SellerRegistrationInviteService $invites,
    ): RedirectResponse {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
            'send_registration_link' => ['boolean'],
        ]);

        $seller->load('user');

        $seller->update([
            'status' => SellerStatus::Rejected,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $seller->user->notify(new SellerRejectedNotification($validated['rejection_reason']));

        $flash = ['success' => 'Seller application rejected.'];

        if ($validated['send_registration_link'] ?? false) {
            $invite = $invites->create(
                $request->user(),
                $seller->user->email,
                $seller->user->name,
                'Issued after application rejection.',
                $seller,
            );

            $flash['sellerInviteUrl'] = $invite->registrationUrl();
            $flash['success'] = 'Seller application rejected. A new registration link has been created.';
        }

        return back()->with($flash);
    }

    public function block(Request $request, SellerProfile $seller, SellerAccountService $accounts): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $accounts->block($seller, $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Seller blocked. Their products are hidden from the shop.');
    }

    public function unblock(Request $request, SellerProfile $seller, SellerAccountService $accounts): RedirectResponse
    {
        try {
            $accounts->unblock($seller, $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Seller unblocked. Their products are visible in the shop again.');
    }

    public function destroy(Request $request, SellerProfile $seller, SellerAccountService $accounts): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'confirm_store_name' => ['required', 'string'],
        ]);

        $expectedName = $seller->business_name ?? $seller->store_name ?? '';

        if (strcasecmp(trim($validated['confirm_store_name']), trim($expectedName)) !== 0) {
            return back()->with('error', 'Store name confirmation did not match. Account was not deleted.');
        }

        try {
            $accounts->delete($seller, $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.sellers.index', ['status' => 'all'])
            ->with('success', 'Seller account deleted. Their listings were removed from the marketplace.');
    }

    public function disablePaymentMethod(
        Request $request,
        SellerProfile $seller,
        SellerPaymentMethod $method,
        SellerPaymentMethodSecurityService $security,
    ): RedirectResponse {
        abort_unless($method->seller_profile_id === $seller->id, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $security->disable($method, $request->user(), $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment method disabled. Seller cannot add new payment methods until you unlock or re-enable.');
    }

    public function enablePaymentMethod(
        Request $request,
        SellerProfile $seller,
        SellerPaymentMethod $method,
        SellerPaymentMethodSecurityService $security,
    ): RedirectResponse {
        abort_unless($method->seller_profile_id === $seller->id, 404);

        try {
            $security->enable($method, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment method enabled again.');
    }

    public function unlockPaymentMethods(
        Request $request,
        SellerProfile $seller,
        SellerPaymentMethodSecurityService $security,
    ): RedirectResponse {
        if (! $seller->paymentMethodsAreLocked()) {
            return back()->with('error', 'Payment method setup is not locked for this seller.');
        }

        $security->unlockPaymentSetup($seller);

        return back()->with('success', 'Seller can add payment methods again. Disabled methods stay blocked until re-enabled.');
    }
}
