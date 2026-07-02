<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Notifications\SellerApprovedNotification;
use App\Notifications\SellerRejectedNotification;
use App\Services\SellerAccountService;
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

        $sellers = SellerProfile::with('user')
            ->whereHas('user')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sellers/index', [
            'sellers' => $sellers,
            'status' => $status,
        ]);
    }

    public function show(SellerProfile $seller): Response
    {
        $seller->load('user');

        return Inertia::render('admin/sellers/show', [
            'seller' => $seller,
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
}
