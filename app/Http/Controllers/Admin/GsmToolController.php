<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GsmOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\GsmOrder;
use App\Models\GsmService;
use App\Models\GsmServiceField;
use App\Services\GsmToolService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GsmToolController extends Controller
{
    public function __construct(private GsmToolService $gsm) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $query = GsmOrder::query()->with(['user:id,name,email,mobile', 'fieldValues'])->latest();

        if (in_array($status, array_column(GsmOrderStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(20)->withQueryString()->through(
            fn (GsmOrder $order) => $this->gsm->orderPayload($order)
        );

        return Inertia::render('admin/gsm-tools/index', [
            'orders' => $orders,
            'filters' => ['status' => $status],
            'pendingCount' => $this->gsm->pendingAdminCount(),
        ]);
    }

    public function show(GsmOrder $gsmOrder): Response
    {
        return Inertia::render('admin/gsm-tools/show', [
            'order' => $this->gsm->orderPayload($gsmOrder, withHistory: true),
        ]);
    }

    public function process(Request $request, GsmOrder $gsmOrder): RedirectResponse
    {
        $this->gsm->markProcessing($gsmOrder, $request->user(), $request->input('note'));

        return back()->with('success', 'Order marked as processing.');
    }

    public function complete(Request $request, GsmOrder $gsmOrder): RedirectResponse
    {
        $validated = $request->validate([
            'result_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->gsm->complete($gsmOrder, $request->user(), $validated['result_note'] ?? null);

        return back()->with('success', 'Order completed.');
    }

    public function fail(Request $request, GsmOrder $gsmOrder): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->gsm->fail($gsmOrder, $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'Order failed. Wallet refunded.');
    }

    public function cancel(Request $request, GsmOrder $gsmOrder): RedirectResponse
    {
        $this->gsm->cancel($gsmOrder, $request->user(), $request->input('note'));

        return back()->with('success', 'Order cancelled. Wallet refunded.');
    }

    public function services(): Response
    {
        $services = GsmService::query()
            ->with('fields')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (GsmService $service) {
                $payload = $this->gsm->servicePayload($service);
                $payload['fields'] = $service->fields->map(fn (GsmServiceField $f) => $this->gsm->fieldPayload($f))->values()->all();

                return $payload;
            })
            ->values()
            ->all();

        return Inertia::render('admin/gsm-tools/services', [
            'services' => $services,
            'fieldTypes' => GsmServiceField::TYPES,
        ]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price_ghs' => ['required', 'numeric', 'min:1', 'max:50000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['nullable', 'boolean'],
            'fields' => ['nullable', 'array', 'max:20'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:120'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:160'],
            'fields.*.type' => ['nullable', Rule::in(GsmServiceField::TYPES)],
            'fields.*.required' => ['nullable', 'boolean'],
        ]);

        $this->gsm->createService($validated, $validated['fields'] ?? []);

        return back()->with('success', 'GSM service created.');
    }

    public function updateService(Request $request, GsmService $gsmService): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price_ghs' => ['required', 'numeric', 'min:1', 'max:50000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['nullable', 'boolean'],
            'fields' => ['nullable', 'array', 'max:20'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:120'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:160'],
            'fields.*.type' => ['nullable', Rule::in(GsmServiceField::TYPES)],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.active' => ['nullable', 'boolean'],
        ]);

        $this->gsm->updateService($gsmService, $validated, $validated['fields'] ?? []);

        return back()->with('success', 'GSM service updated.');
    }
}
