<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\GsmOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\GsmOrder;
use App\Models\GsmService;
use App\Models\GsmServiceField;
use App\Services\GsmToolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GsmToolController extends Controller
{
    public function __construct(private GsmToolService $gsm) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString();
        $query = GsmOrder::query()->with(['user:id,name,email,mobile', 'fieldValues'])->latest();

        if (in_array($status, array_column(GsmOrderStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $orders->getCollection()->map(fn (GsmOrder $o) => $this->gsm->orderPayload($o))->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'pending_count' => $this->gsm->pendingAdminCount(),
        ]);
    }

    public function show(GsmOrder $gsmOrder): JsonResponse
    {
        return response()->json([
            'order' => $this->gsm->orderPayload($gsmOrder, withHistory: true),
        ]);
    }

    public function process(Request $request, GsmOrder $gsmOrder): JsonResponse
    {
        $order = $this->gsm->markProcessing($gsmOrder, $request->user(), $request->input('note'));

        return response()->json(['order' => $this->gsm->orderPayload($order, withHistory: true)]);
    }

    public function complete(Request $request, GsmOrder $gsmOrder): JsonResponse
    {
        $validated = $request->validate(['result_note' => ['nullable', 'string', 'max:2000']]);
        $order = $this->gsm->complete($gsmOrder, $request->user(), $validated['result_note'] ?? null);

        return response()->json(['order' => $this->gsm->orderPayload($order, withHistory: true)]);
    }

    public function fail(Request $request, GsmOrder $gsmOrder): JsonResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $order = $this->gsm->fail($gsmOrder, $request->user(), $validated['reason'] ?? null);

        return response()->json(['order' => $this->gsm->orderPayload($order, withHistory: true)]);
    }

    public function cancel(Request $request, GsmOrder $gsmOrder): JsonResponse
    {
        $order = $this->gsm->cancel($gsmOrder, $request->user(), $request->input('note'));

        return response()->json(['order' => $this->gsm->orderPayload($order, withHistory: true)]);
    }

    public function services(): JsonResponse
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
            ->values();

        return response()->json(['services' => $services, 'field_types' => GsmServiceField::TYPES]);
    }

    public function storeService(Request $request): JsonResponse
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

        $service = $this->gsm->createService($validated, $validated['fields'] ?? []);

        return response()->json(['service' => $this->gsm->servicePayload($service->load('fields'))], 201);
    }

    public function updateService(Request $request, GsmService $gsmService): JsonResponse
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

        $service = $this->gsm->updateService($gsmService, $validated, $validated['fields'] ?? []);

        return response()->json(['service' => $this->gsm->servicePayload($service->load('fields'))]);
    }
}
