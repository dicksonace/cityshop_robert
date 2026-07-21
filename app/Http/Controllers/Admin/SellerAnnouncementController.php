<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverAdminSellerAnnouncement;
use App\Models\AdminAnnouncement;
use App\Models\SellerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerAnnouncementController extends Controller
{
    public function index(): Response
    {
        $announcements = AdminAnnouncement::with('admin:id,name')
            ->latest()
            ->paginate(20)
            ->through(fn (AdminAnnouncement $a) => [
                'id' => $a->id,
                'audience' => $a->audience,
                'title' => $a->title,
                'body' => $a->body,
                'send_email' => $a->send_email,
                'recipients_count' => $a->recipients_count,
                'seller_profile_ids' => $a->seller_profile_ids ?? [],
                'admin' => $a->admin?->only(['id', 'name']),
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/announcements/index', [
            'announcements' => $announcements,
        ]);
    }

    public function create(): Response
    {
        $sellers = SellerProfile::query()
            ->with('user:id,name,email,mobile')
            ->where('status', SellerStatus::Approved)
            ->orderBy('business_name')
            ->orderBy('store_name')
            ->get()
            ->map(fn (SellerProfile $profile) => [
                'id' => $profile->id,
                'name' => $profile->displayName(),
                'email' => $profile->user?->email,
                'mobile' => $profile->user?->mobile,
            ]);

        return Inertia::render('admin/announcements/create', [
            'sellers' => $sellers,
        ]);
    }

    public function store(Request $request): RedirectResponse
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
            return back()->withErrors(['seller_ids' => 'Select at least one seller.'])->withInput();
        }

        if ($validated['audience'] === 'one' && count($sellerIds) !== 1) {
            return back()->withErrors(['seller_ids' => 'Select exactly one seller.'])->withInput();
        }

        if ($validated['audience'] !== 'all') {
            $approvedCount = SellerProfile::whereIn('id', $sellerIds)
                ->where('status', SellerStatus::Approved)
                ->count();

            if ($approvedCount !== count($sellerIds)) {
                return back()->withErrors(['seller_ids' => 'Only approved sellers can receive announcements.'])->withInput();
            }
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

        // Deliver immediately when queue worker is not running (local/XAMPP).
        DeliverAdminSellerAnnouncement::dispatchSync($announcement);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Message sent to sellers.');
    }
}
