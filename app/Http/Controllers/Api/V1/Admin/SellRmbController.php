<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SellRmbStatus;
use App\Http\Controllers\Controller;
use App\Models\SellRmbFormField;
use App\Models\SellRmbRate;
use App\Models\SellRmbReceiveMethod;
use App\Models\SellRmbTransfer;
use App\Services\SellRmbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SellRmbController extends Controller
{
    public function __construct(private SellRmbService $sellRmb) {}

    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->get('status', 'open');
        $search = trim((string) $request->get('q', ''));

        $items = SellRmbTransfer::query()
            ->with(['user:id,name,email,mobile', 'receiveMethod'])
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
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $items->getCollection()->map(fn (SellRmbTransfer $item) => $this->sellRmb->transferPayload($item, true))->values(),
            'meta' => AdminJson::meta($items),
            'status' => $status,
            'dashboard' => $this->sellRmb->dashboard(),
        ]);
    }

    public function show(SellRmbTransfer $sellRmbTransfer): JsonResponse
    {
        return response()->json(['data' => $this->sellRmb->transferPayload($sellRmbTransfer, true)]);
    }

    public function verify(Request $request, SellRmbTransfer $sellRmbTransfer): JsonResponse
    {
        return $this->run(fn () => $this->sellRmb->startVerification($sellRmbTransfer, $request->user()), 'RMB verification started.', $sellRmbTransfer);
    }

    public function received(Request $request, SellRmbTransfer $sellRmbTransfer): JsonResponse
    {
        return $this->run(fn () => $this->sellRmb->markRmbReceived($sellRmbTransfer, $request->user()), 'RMB marked as received.', $sellRmbTransfer);
    }

    public function process(Request $request, SellRmbTransfer $sellRmbTransfer): JsonResponse
    {
        return $this->run(fn () => $this->sellRmb->startPayoutProcessing($sellRmbTransfer, $request->user()), 'Payout processing started.', $sellRmbTransfer);
    }

    public function paid(Request $request, SellRmbTransfer $sellRmbTransfer): JsonResponse
    {
        return $this->run(fn () => $this->sellRmb->markPaid($sellRmbTransfer, $request->user(), $request), 'Payout marked as paid.', $sellRmbTransfer);
    }

    public function complete(Request $request, SellRmbTransfer $sellRmbTransfer): JsonResponse
    {
        return $this->run(fn () => $this->sellRmb->complete($sellRmbTransfer, $request->user()), 'Sell RMB completed.', $sellRmbTransfer);
    }

    public function reject(Request $request, SellRmbTransfer $sellRmbTransfer): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->run(fn () => $this->sellRmb->reject($sellRmbTransfer, $request->user(), $validated['reason']), 'Sell RMB rejected.', $sellRmbTransfer);
    }

    public function fail(Request $request, SellRmbTransfer $sellRmbTransfer): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->run(fn () => $this->sellRmb->fail($sellRmbTransfer, $request->user(), $validated['reason']), 'Sell RMB marked as failed.', $sellRmbTransfer);
    }

    public function cancel(Request $request, SellRmbTransfer $sellRmbTransfer): JsonResponse
    {
        return $this->run(fn () => $this->sellRmb->cancel($sellRmbTransfer, $request->user(), $request->input('note')), 'Sell RMB cancelled.', $sellRmbTransfer);
    }

    public function note(Request $request, SellRmbTransfer $sellRmbTransfer): JsonResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->sellRmb->addNote($sellRmbTransfer, $request->user(), $validated['note']);

        return response()->json(['message' => 'Note saved.', 'data' => $this->sellRmb->transferPayload($sellRmbTransfer->fresh(), true)]);
    }

    public function settings(): JsonResponse
    {
        $settings = $this->sellRmb->settings();

        $rate = $this->sellRmb->currentRate();

        return response()->json([
            'settings' => [
                'enabled' => $settings->enabled,
                'instructions' => $settings->instructions,
                'receive_instructions' => $settings->receive_instructions,
            ],
            'current_rate' => $rate ? $this->sellRmb->ratePayload($rate) : null,
            'rates' => SellRmbRate::query()->latest('id')->limit(10)->get()->map(fn (SellRmbRate $rate) => $this->sellRmb->ratePayload($rate)),
            'methods' => SellRmbReceiveMethod::query()->orderBy('sort_order')->get()->map(fn (SellRmbReceiveMethod $m) => $this->sellRmb->methodPayload($m)),
            'fields' => SellRmbFormField::query()->orderBy('group')->orderBy('sort_order')->get()->map(fn (SellRmbFormField $f) => $this->sellRmb->fieldPayload($f)),
            'open' => $this->sellRmb->isOpen(),
            'readiness' => $this->sellRmb->readinessPayload(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'receive_instructions' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->sellRmb->settings()->update([
            'enabled' => $validated['enabled'],
            'instructions' => $validated['instructions'] ?? null,
            'receive_instructions' => $validated['receive_instructions'] ?? null,
        ]);

        return response()->json([
            'message' => $validated['enabled'] ? 'RMB → GHS set to Live.' : 'RMB → GHS paused.',
        ]);
    }

    public function publishRate(Request $request): JsonResponse
    {
        foreach (['daily_max_rmb', 'monthly_max_rmb', 'max_per_day', 'approval_above_rmb', 'effective_from', 'usd_per_rmb', 'ghs_per_usd'] as $key) {
            if ($request->input($key) === '') {
                $request->merge([$key => null]);
            }
        }
        $validated = $request->validate([
            'ghs_per_rmb' => ['required', 'numeric', 'min:0.0001'],
            'usd_per_rmb' => ['nullable', 'numeric', 'min:0.000001'],
            'ghs_per_usd' => ['nullable', 'numeric', 'min:0.0001'],
            'fee_mode' => ['required', 'in:flat,percent'],
            'fee_value' => ['required', 'numeric', 'min:0'],
            'min_rmb' => ['required', 'numeric', 'min:1'],
            'max_rmb' => ['required', 'numeric', 'gte:min_rmb'],
            'daily_max_rmb' => ['nullable', 'numeric', 'min:1'],
            'monthly_max_rmb' => ['nullable', 'numeric', 'min:1'],
            'max_per_day' => ['nullable', 'integer', 'min:1'],
            'approval_above_rmb' => ['nullable', 'numeric', 'min:1'],
            'effective_from' => ['nullable', 'date'],
        ]);
        $this->sellRmb->publishRate($request->user(), $validated);

        return response()->json(['message' => 'Buying rate published. Existing requests keep their locked rate.']);
    }

    public function storeMethod(Request $request): JsonResponse
    {
        $validated = $this->methodRules($request);
        $validated['qr_path'] = $this->storeQr($request);
        $validated['sort_order'] = $validated['sort_order'] ?? ((int) SellRmbReceiveMethod::max('sort_order')) + 1;
        unset($validated['qr']);

        if (in_array($validated['type'], ['alipay', 'wechat'], true) && blank($validated['qr_path'])) {
            throw ValidationException::withMessages([
                'qr' => 'Upload a QR code for Alipay or WeChat receive methods.',
            ]);
        }

        $method = SellRmbReceiveMethod::create($validated);

        return response()->json([
            'message' => 'Alipay receive method saved.',
            'data' => $this->sellRmb->methodPayload($method),
        ], 201);
    }

    public function updateMethod(Request $request, SellRmbReceiveMethod $method): JsonResponse
    {
        $validated = $this->methodRules($request, false);
        if ($request->hasFile('qr')) {
            $file = $request->file('qr');
            if ($file instanceof UploadedFile) {
                $method = $this->sellRmb->replaceMethodQr($method, $file);
            }
            unset($validated['qr']);
        }
        if ($validated !== []) {
            unset($validated['qr']);
            $method->update($validated);
            $method = $method->fresh();
        }

        return response()->json([
            'message' => 'Receive method updated.',
            'data' => $this->sellRmb->methodPayload($method),
        ]);
    }

    public function replaceMethodQr(Request $request, SellRmbReceiveMethod $method): JsonResponse
    {
        $validated = $request->validate([
            'qr' => ['required', 'image', 'max:4096'],
        ]);

        $file = $validated['qr'];
        if (! $file instanceof UploadedFile) {
            return response()->json(['message' => 'Invalid QR image.'], 422);
        }

        $method = $this->sellRmb->replaceMethodQr($method, $file);

        return response()->json([
            'message' => 'Alipay QR updated. Buyers see the new code on their next refresh.',
            'data' => $this->sellRmb->methodPayload($method),
        ]);
    }

    public function deactivateMethod(SellRmbReceiveMethod $method): JsonResponse
    {
        $method->update(['active' => false]);

        return response()->json(['message' => 'Receive method deactivated.']);
    }

    public function deactivateField(SellRmbFormField $field): JsonResponse
    {
        $field->update(['active' => false]);

        return response()->json(['message' => 'Form field deactivated.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function methodRules(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        $validated = $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'type' => [$required, 'in:alipay,wechat,bank,other'],
            'account_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:80'],
            'network' => ['nullable', 'string', 'max:40'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'proof_required' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'qr' => ['nullable', 'image', 'max:4096'],
        ]);

        $validated['proof_required'] = $request->boolean('proof_required', true);
        $validated['active'] = $request->boolean('active', true);

        return $validated;
    }

    private function storeQr(Request $request): ?string
    {
        if (! $request->hasFile('qr')) {
            return null;
        }

        $file = $request->file('qr');
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file->store('sell-rmb/methods', 'public');
    }

    private function run(callable $action, string $message, SellRmbTransfer $transfer): JsonResponse
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
            'data' => $this->sellRmb->transferPayload($transfer->fresh(), true),
        ]);
    }
}
