<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Notifications\SellerApprovedNotification;
use App\Notifications\SellerRejectedNotification;
use App\Notifications\SellerSuspendedNotification;
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

    public function approve(Request $request, SellerProfile $seller): RedirectResponse
    {
        $seller->update([
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'rejection_reason' => null,
        ]);

        $seller->user->notify(new SellerApprovedNotification($seller));

        return back()->with('success', 'Seller approved successfully.');
    }

    public function reject(Request $request, SellerProfile $seller): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $seller->update([
            'status' => SellerStatus::Rejected,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $seller->user->notify(new SellerRejectedNotification($validated['rejection_reason']));

        return back()->with('success', 'Seller application rejected.');
    }

    public function block(Request $request, SellerProfile $seller): RedirectResponse
    {
        if ($seller->status !== SellerStatus::Approved) {
            return back()->with('error', 'Only approved sellers can be blocked.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $seller->update([
            'status' => SellerStatus::Suspended,
            'rejection_reason' => $validated['reason'],
        ]);

        $seller->user->notify(new SellerSuspendedNotification($validated['reason']));

        return back()->with('success', 'Seller blocked. Their products are hidden from the shop.');
    }

    public function unblock(Request $request, SellerProfile $seller): RedirectResponse
    {
        if ($seller->status !== SellerStatus::Suspended) {
            return back()->with('error', 'This seller is not blocked.');
        }

        $seller->update([
            'status' => SellerStatus::Approved,
            'rejection_reason' => null,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Seller unblocked. Their products are visible in the shop again.');
    }
}
