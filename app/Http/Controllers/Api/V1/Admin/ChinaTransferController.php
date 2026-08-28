<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ChinaTransferStatus;
use App\Http\Controllers\Controller;
use App\Models\ChinaTransfer;
use App\Models\ChinaTransferFormField;
use App\Models\ChinaTransferPaymentMethod;
use App\Models\ChinaTransferRate;
use App\Services\ChinaTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChinaTransferController extends Controller
{
    public function __construct(private ChinaTransferService $transfers) {}

    public function index(Request $request): JsonResponse
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
            ->paginate(20);

        return response()->json([
            'data' => $items->getCollection()->map(fn (ChinaTransfer $item) => $this->transfers->transferPayload($item, true))->values(),
            'meta' => AdminJson::meta($items),
            'status' => $status,
            'dashboard' => $this->transfers->dashboard(),
        ]);
    }

    public function show(ChinaTransfer $chinaTransfer): JsonResponse
    {
        return response()->json(['data' => $this->transfers->transferPayload($chinaTransfer, true)]);
    }

    public function verify(Request $request, ChinaTransfer $chinaTransfer): JsonResponse
    {
        return $this->run(fn () => $this->transfers->verifyPayment($chinaTransfer, $request->user()), 'Payment verified.', $chinaTransfer);
    }

    public function reject(Request $request, ChinaTransfer $chinaTransfer): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->run(fn () => $this->transfers->rejectPayment($chinaTransfer, $request->user(), $validated['reason']), 'Payment rejected.', $chinaTransfer);
    }

    public function process(Request $request, ChinaTransfer $chinaTransfer): JsonResponse
    {
        return $this->run(fn () => $this->transfers->startProcessing($chinaTransfer, $request->user()), 'Transfer is now processing.', $chinaTransfer);
    }

    public function markSent(Request $request, ChinaTransfer $chinaTransfer): JsonResponse
    {
        return $this->run(fn () => $this->transfers->markSent($chinaTransfer, $request->user(), $request), 'RMB sent. Buyer can view the proof.', $chinaTransfer);
    }

    public function complete(Request $request, ChinaTransfer $chinaTransfer): JsonResponse
    {
        return $this->run(fn () => $this->transfers->complete($chinaTransfer, $request->user()), 'Transfer completed.', $chinaTransfer);
    }

    public function completeWithProof(Request $request, ChinaTransfer $chinaTransfer): JsonResponse
    {
        return $this->run(
            fn () => $this->transfers->uploadProofAndComplete($chinaTransfer, $request->user(), $request),
            'Proof uploaded. Transfer completed.',
            $chinaTransfer,
        );
    }

    public function fail(Request $request, ChinaTransfer $chinaTransfer): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->run(fn () => $this->transfers->fail($chinaTransfer, $request->user(), $validated['reason']), 'Transfer marked as failed.', $chinaTransfer);
    }

    public function cancel(Request $request, ChinaTransfer $chinaTransfer): JsonResponse
    {
        return $this->run(fn () => $this->transfers->cancel($chinaTransfer, $request->user(), $request->input('note')), 'Transfer cancelled.', $chinaTransfer);
    }

    public function note(Request $request, ChinaTransfer $chinaTransfer): JsonResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->transfers->addNote($chinaTransfer, $request->user(), $validated['note']);

        return response()->json(['message' => 'Note saved.', 'data' => $this->transfers->transferPayload($chinaTransfer->fresh(), true)]);
    }

    public function settings(): JsonResponse
    {
        $settings = $this->transfers->settings();

        return response()->json([
            'settings' => [
                'enabled' => $settings->enabled,
                'channel' => 'alipay',
                'instructions' => $settings->instructions,
                'transfer_open_time' => $settings->transfer_open_time ? substr((string) $settings->transfer_open_time, 0, 5) : '04:30',
                'transfer_close_time' => $settings->transfer_close_time ? substr((string) $settings->transfer_close_time, 0, 5) : '17:00',
            ],
            'current_rate' => ($rate = $this->transfers->currentRate())
                ? $this->transfers->ratePayload($rate)
                : null,
            'rates' => ChinaTransferRate::query()->latest('id')->limit(10)->get()->map(fn (ChinaTransferRate $rate) => $this->transfers->ratePayload($rate)),
            'methods' => ChinaTransferPaymentMethod::query()->orderBy('sort_order')->get()->map(fn (ChinaTransferPaymentMethod $m) => $this->transfers->methodPayload($m)),
            'fields' => ChinaTransferFormField::query()->orderBy('group')->orderBy('sort_order')->get()->map(fn (ChinaTransferFormField $f) => $this->transfers->fieldPayload($f)),
            'open' => $this->transfers->isOpen(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'transfer_open_time' => ['nullable', 'date_format:H:i'],
            'transfer_close_time' => ['nullable', 'date_format:H:i', 'different:transfer_open_time'],
        ]);
        $payload = [
            'enabled' => $validated['enabled'],
            'channel' => 'alipay',
            'instructions' => $validated['instructions'] ?? null,
        ];
        if (isset($validated['transfer_open_time'])) {
            $payload['transfer_open_time'] = $validated['transfer_open_time'].':00';
        }
        if (isset($validated['transfer_close_time'])) {
            $payload['transfer_close_time'] = $validated['transfer_close_time'].':00';
        }
        $this->transfers->settings()->update($payload);

        return response()->json([
            'message' => $validated['enabled']
                ? 'GHS → RMB set to Live.'
                : 'GHS → RMB paused.',
        ]);
    }

    public function publishRate(Request $request): JsonResponse
    {
        foreach (['daily_max_ghs', 'monthly_max_ghs', 'max_per_day', 'approval_above_ghs', 'effective_from'] as $key) {
            if ($request->input($key) === '') {
                $request->merge([$key => null]);
            }
        }
        $validated = $request->validate([
            'rmb_per_ghs' => ['nullable', 'numeric', 'min:0.0001'],
            'ghs_per_rmb' => ['nullable', 'numeric', 'min:0.0001'],
            'fee_mode' => ['required', 'in:flat,percent'],
            'fee_value' => ['required', 'numeric', 'min:0'],
            'min_ghs' => ['required', 'numeric', 'min:1'],
            'max_ghs' => ['required', 'numeric', 'gte:min_ghs'],
            'daily_max_ghs' => ['nullable', 'numeric', 'min:1'],
            'monthly_max_ghs' => ['nullable', 'numeric', 'min:1'],
            'max_per_day' => ['nullable', 'integer', 'min:1'],
            'approval_above_ghs' => ['nullable', 'numeric', 'min:1'],
            'effective_from' => ['nullable', 'date'],
        ]);

        if (isset($validated['rmb_per_ghs']) && (float) $validated['rmb_per_ghs'] > 0) {
            $rmbPerGhs = round((float) $validated['rmb_per_ghs'], 3);
            $validated['ghs_per_rmb'] = round(1 / $rmbPerGhs, 6);
        }

        if (! isset($validated['ghs_per_rmb']) || (float) $validated['ghs_per_rmb'] <= 0) {
            return response()->json(['message' => 'Enter a valid GHS → RMB rate (e.g. 0.559).'], 422);
        }

        unset($validated['rmb_per_ghs']);

        $rate = $this->transfers->publishRate($request->user(), $validated);
        $rmbPerGhs = $rate->rmbPerGhs();

        return response()->json([
            'message' => sprintf(
                'Rate published: 1 GHS → ¥%s RMB (1 CNY ≈ GH₵%s). Existing transfers keep their locked rate.',
                number_format($rmbPerGhs, 3, '.', ''),
                number_format($rate->effectiveGhsPerRmb(), 3, '.', ''),
            ),
        ]);
    }

    public function deactivateMethod(ChinaTransferPaymentMethod $method): JsonResponse
    {
        $method->update(['active' => false]);

        return response()->json(['message' => 'Payment method deactivated.']);
    }

    public function deactivateField(ChinaTransferFormField $field): JsonResponse
    {
        $field->update(['active' => false]);

        return response()->json(['message' => 'Form field deactivated.']);
    }

    private function run(callable $action, string $message, ChinaTransfer $transfer): JsonResponse
    {
        try {
            $action();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $message,
            'data' => $this->transfers->transferPayload($transfer->fresh(), true),
        ]);
    }
}
