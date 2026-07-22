<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverAdminBuyerAnnouncement;
use App\Models\AdminBuyerAnnouncement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerAnnouncementController extends Controller
{
    public function index(): Response
    {
        $announcements = AdminBuyerAnnouncement::with('admin:id,name')
            ->latest()
            ->paginate(20)
            ->through(fn (AdminBuyerAnnouncement $a) => [
                'id' => $a->id,
                'audience' => $a->audience,
                'title' => $a->title,
                'body' => $a->body,
                'send_email' => $a->send_email,
                'recipients_count' => $a->recipients_count,
                'buyer_user_ids' => $a->buyer_user_ids ?? [],
                'admin' => $a->admin?->only(['id', 'name']),
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/buyer-announcements/index', [
            'announcements' => $announcements,
        ]);
    }

    public function create(): Response
    {
        $buyers = User::query()
            ->where('role', UserRole::Buyer)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'mobile'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
            ]);

        return Inertia::render('admin/buyer-announcements/create', [
            'buyers' => $buyers,
        ]);
    }

    public function store(Request $request): RedirectResponse
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
            return back()->withErrors(['buyer_ids' => 'Select at least one buyer.'])->withInput();
        }

        if ($validated['audience'] === 'one' && count($buyerIds) !== 1) {
            return back()->withErrors(['buyer_ids' => 'Select exactly one buyer.'])->withInput();
        }

        if ($validated['audience'] !== 'all') {
            $buyerCount = User::whereIn('id', $buyerIds)
                ->where('role', UserRole::Buyer)
                ->count();

            if ($buyerCount !== count($buyerIds)) {
                return back()->withErrors(['buyer_ids' => 'Only buyer accounts can receive these messages.'])->withInput();
            }
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

        return redirect()
            ->route('admin.buyer-announcements.index')
            ->with('success', 'Message sent to buyers.');
    }
}
