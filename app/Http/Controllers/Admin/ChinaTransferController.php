<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChinaTransferStatus;
use App\Http\Controllers\Controller;
use App\Models\ChinaTransfer;
use App\Services\ChinaTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChinaTransferController extends Controller
{
    public function __construct(private ChinaTransferService $transfers) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->get('status', 'open');
        $search = trim((string) $request->get('q', ''));

        $items = ChinaTransfer::query()
            ->with(['user:id,name,email,mobile', 'paymentMethod'])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', [
                ChinaTransferStatus::PendingPayment,
                ChinaTransferStatus::PaymentSubmitted,
                ChinaTransferStatus::PaymentVerification,
                ChinaTransferStatus::Processing,
                ChinaTransferStatus::RmbSent,
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
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ChinaTransfer $item) => $this->transfers->transferPayload($item, true));

        return Inertia::render('admin/china-transfer/index', [
            'transfers' => $items,
            'status' => $status,
            'search' => $search,
            'dashboard' => $this->transfers->dashboard(),
        ]);
    }

    public function show(ChinaTransfer $chinaTransfer): Response
    {
        return Inertia::render('admin/china-transfer/show', [
            'transfer' => $this->transfers->transferPayload($chinaTransfer, true),
        ]);
    }

    public function verify(Request $request, ChinaTransfer $chinaTransfer): RedirectResponse
    {
        $this->transfers->verifyPayment($chinaTransfer, $request->user());

        return back()->with('success', 'Payment verified.');
    }

    public function reject(Request $request, ChinaTransfer $chinaTransfer): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->transfers->rejectPayment($chinaTransfer, $request->user(), $validated['reason']);

        return back()->with('success', 'Payment rejected.');
    }

    public function process(Request $request, ChinaTransfer $chinaTransfer): RedirectResponse
    {
        $this->transfers->startProcessing($chinaTransfer, $request->user());

        return back()->with('success', 'Transfer is now processing.');
    }

    public function markSent(Request $request, ChinaTransfer $chinaTransfer): RedirectResponse
    {
        $this->transfers->markSent($chinaTransfer, $request->user(), $request);

        return back()->with('success', 'RMB sent. Buyer can view the proof.');
    }

    public function complete(Request $request, ChinaTransfer $chinaTransfer): RedirectResponse
    {
        $this->transfers->complete($chinaTransfer, $request->user());

        return back()->with('success', 'Transfer completed.');
    }

    public function completeWithProof(Request $request, ChinaTransfer $chinaTransfer): RedirectResponse
    {
        $this->transfers->uploadProofAndComplete($chinaTransfer, $request->user(), $request);

        return back()->with('success', 'Proof uploaded. Transfer completed.');
    }

    public function fail(Request $request, ChinaTransfer $chinaTransfer): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->transfers->fail($chinaTransfer, $request->user(), $validated['reason']);

        return back()->with('success', 'Transfer marked as failed.');
    }

    public function cancel(Request $request, ChinaTransfer $chinaTransfer): RedirectResponse
    {
        $this->transfers->cancel($chinaTransfer, $request->user(), $request->input('note'));

        return back()->with('success', 'Transfer cancelled.');
    }

    public function note(Request $request, ChinaTransfer $chinaTransfer): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $this->transfers->addNote($chinaTransfer, $request->user(), $validated['note']);

        return back()->with('success', 'Note saved.');
    }
}
