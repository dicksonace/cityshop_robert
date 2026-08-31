<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellRmbStatus;
use App\Http\Controllers\Controller;
use App\Models\SellRmbTransfer;
use App\Services\SellRmbService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellRmbController extends Controller
{
    public function __construct(private SellRmbService $sellRmb) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->get('status', 'open');
        $search = trim((string) $request->get('q', ''));

        $items = SellRmbTransfer::query()
            ->with(['user:id,name,email,mobile', 'receiveMethod', 'fieldValues.field'])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', [
                SellRmbStatus::Submitted,
                SellRmbStatus::RmbVerification,
                SellRmbStatus::RmbReceived,
                SellRmbStatus::PayoutProcessing,
                SellRmbStatus::Paid,
            ]))
            ->when($status !== 'open' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status === 'open', function ($q) {
                $q->orderByRaw("CASE status
                    WHEN 'payout_processing' THEN 1
                    WHEN 'rmb_received' THEN 2
                    WHEN 'paid' THEN 3
                    WHEN 'rmb_verification' THEN 4
                    WHEN 'submitted' THEN 5
                    ELSE 6 END")
                    ->latest('id');
            }, fn ($q) => $q->latest())
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SellRmbTransfer $item) => $this->sellRmb->transferPayload($item, true));

        return Inertia::render('admin/sell-rmb/index', [
            'transfers' => $items,
            'status' => $status,
            'search' => $search,
            'dashboard' => $this->sellRmb->dashboard(),
        ]);
    }

    public function show(SellRmbTransfer $sellRmbTransfer): Response
    {
        return Inertia::render('admin/sell-rmb/show', [
            'transfer' => $this->sellRmb->transferPayload($sellRmbTransfer, true),
        ]);
    }

    public function verify(Request $request, SellRmbTransfer $sellRmbTransfer): RedirectResponse
    {
        $this->sellRmb->startVerification($sellRmbTransfer, $request->user());

        return back()->with('success', 'RMB verification started.');
    }

    public function received(Request $request, SellRmbTransfer $sellRmbTransfer): RedirectResponse
    {
        $this->sellRmb->markRmbReceived($sellRmbTransfer, $request->user());

        return back()->with('success', 'RMB marked as received.');
    }

    public function process(Request $request, SellRmbTransfer $sellRmbTransfer): RedirectResponse
    {
        $this->sellRmb->startPayoutProcessing($sellRmbTransfer, $request->user());

        return back()->with('success', 'Payout processing started.');
    }

    public function markProcessing(Request $request, SellRmbTransfer $sellRmbTransfer): RedirectResponse
    {
        $this->sellRmb->markReadyForPayout($sellRmbTransfer, $request->user());

        return back()->with('success', 'Marked for MoMo payout. Send GHS to the buyer, then approve.');
    }

    public function approvePayout(Request $request, SellRmbTransfer $sellRmbTransfer): RedirectResponse
    {
        $this->sellRmb->approvePayout($sellRmbTransfer, $request->user(), $request);

        return back()->with('success', 'MoMo payout approved and sell completed.');
    }

    public function paid(Request $request, SellRmbTransfer $sellRmbTransfer): RedirectResponse
    {
        $this->sellRmb->markPaid($sellRmbTransfer, $request->user(), $request);

        return back()->with('success', 'Payout marked as paid.');
    }

    public function complete(Request $request, SellRmbTransfer $sellRmbTransfer): RedirectResponse
    {
        $this->sellRmb->complete($sellRmbTransfer, $request->user());

        return back()->with('success', 'Sell RMB completed.');
    }

    public function reject(Request $request, SellRmbTransfer $sellRmbTransfer): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->sellRmb->reject($sellRmbTransfer, $request->user(), $validated['reason']);

        return back()->with('success', 'Sell RMB rejected.');
    }

    public function fail(Request $request, SellRmbTransfer $sellRmbTransfer): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->sellRmb->fail($sellRmbTransfer, $request->user(), $validated['reason']);

        return back()->with('success', 'Sell RMB marked as failed.');
    }

    public function cancel(Request $request, SellRmbTransfer $sellRmbTransfer): RedirectResponse
    {
        $this->sellRmb->cancel($sellRmbTransfer, $request->user(), $request->input('note'));

        return back()->with('success', 'Sell RMB cancelled.');
    }

    public function note(Request $request, SellRmbTransfer $sellRmbTransfer): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $this->sellRmb->addNote($sellRmbTransfer, $request->user(), $validated['note']);

        return back()->with('success', 'Note saved.');
    }
}
