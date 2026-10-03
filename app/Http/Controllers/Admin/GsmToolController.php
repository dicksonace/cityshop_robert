<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GsmOrderStatus;
use App\Enums\GsmServiceType;
use App\Http\Controllers\Controller;
use App\Models\GsmOrder;
use App\Models\GsmService;
use App\Models\GsmServiceField;
use App\Models\GsmServiceGroup;
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
        $type = $request->string('type')->toString();
        $query = GsmOrder::query()->with(['user:id,name,email,mobile', 'service', 'fieldValues'])->latest();

        if (in_array($status, array_column(GsmOrderStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        if (in_array($type, array_map(fn (GsmServiceType $item) => $item->value, GsmServiceType::groups()), true)) {
            $query->whereHas('service', fn ($q) => $q->where('service_type', $type));
        }

        $orders = $query->paginate(20)->withQueryString()->through(
            fn (GsmOrder $order) => $this->gsm->orderPayload($order)
        );

        return Inertia::render('admin/gsm-tools/index', [
            'orders' => $orders,
            'filters' => ['status' => $status, 'type' => $type],
            'serviceTypes' => GsmServiceType::options(),
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

        return back()->with('success', 'Order marked as Processing.');
    }

    public function reply(Request $request, GsmOrder $gsmOrder): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);
        $this->gsm->reply($gsmOrder, $request->user(), $validated['message']);

        return back()->with('success', 'Reply sent to the buyer.');
    }

    public function complete(Request $request, GsmOrder $gsmOrder): RedirectResponse
    {
        $validated = $request->validate([
            'result_note' => ['nullable', 'string', 'max:5000'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);
        $this->gsm->complete(
            $gsmOrder,
            $request->user(),
            $validated['result_note'] ?? $validated['message'] ?? null,
        );

        return back()->with('success', 'Order Completed.');
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

    public function services(Request $request): Response|RedirectResponse
    {
        $type = $request->string('type')->toString();
        $allowed = array_map(fn (GsmServiceType $item) => $item->value, GsmServiceType::groups());
        if (! in_array($type, $allowed, true)) {
            return redirect()->route('admin.gsm-tools.services', ['type' => GsmServiceType::Imei->value]);
        }

        $query = GsmService::query()->with('fields')->orderBy('sort_order')->orderBy('id')->where('service_type', $type);

        $services = $query
            ->get()
            ->map(function (GsmService $service) {
                $payload = $this->gsm->servicePayload($service);
                $payload['fields'] = $service->fields->map(fn (GsmServiceField $f) => $this->gsm->fieldPayload($f))->values()->all();

                return $payload;
            })
            ->values()
            ->all();

        $selected = GsmServiceType::tryFrom($type);
        $active = match ($selected) {
            GsmServiceType::Imei => 'gsm-tools-imei',
            GsmServiceType::Server => 'gsm-tools-server',
            GsmServiceType::Remote => 'gsm-tools-remote',
            GsmServiceType::File => 'gsm-tools-file',
            GsmServiceType::Credit => 'gsm-tools-credit',
            default => 'gsm-tools-imei',
        };

        return Inertia::render('admin/gsm-tools/services', [
            'services' => $services,
            'groups' => GsmServiceGroup::query()
                ->where('service_type', $type)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (GsmServiceGroup $group) => $this->gsm->groupPayload($group))
                ->values()
                ->all(),
            'fieldTypes' => GsmServiceField::TYPES,
            'serviceTypes' => GsmServiceType::options(),
            'selectedType' => $selected && in_array($selected, GsmServiceType::groups(), true) ? $selected->value : 'imei',
            'active' => $active,
        ]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:160'],
            'service_type' => ['required', Rule::enum(GsmServiceType::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'price_ghs' => ['required', 'numeric', 'min:1', 'max:50000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['nullable', 'boolean'],
            'fields' => ['nullable', 'array', 'max:20'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:120'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:160'],
            'fields.*.type' => ['nullable', Rule::in(GsmServiceField::TYPES)],
            'fields.*.required' => ['nullable', 'boolean'],
        ], $this->catalogServiceRules()));

        $service = $this->gsm->createService($validated, $validated['fields'] ?? []);
        $this->gsm->attachServiceImage($service, $request->file('image'));

        return redirect()
            ->route('admin.gsm-tools.services', ['type' => $validated['service_type']])
            ->with('success', 'GSM service created.');
    }

    public function updateService(Request $request, GsmService $gsmService): RedirectResponse
    {
        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:160'],
            'service_type' => ['required', Rule::enum(GsmServiceType::class)],
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
        ], $this->catalogServiceRules()));

        $service = $this->gsm->updateService($gsmService, $validated, $validated['fields'] ?? []);
        $this->gsm->attachServiceImage($service, $request->file('image'));

        return redirect()
            ->route('admin.gsm-tools.services', ['type' => $validated['service_type']])
            ->with('success', 'GSM service updated.');
    }

    public function setServiceActive(Request $request, GsmService $gsmService): RedirectResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $gsmService->active = $validated['active'];
        $gsmService->save();

        return back()->with('success', $gsmService->active ? 'GSM service enabled.' : 'GSM service disabled.');
    }

    public function destroyService(GsmService $gsmService): RedirectResponse
    {
        $type = ($gsmService->service_type ?? GsmServiceType::Imei)->value;
        $this->gsm->deleteService($gsmService);

        return redirect()
            ->route('admin.gsm-tools.services', ['type' => $type])
            ->with('success', 'GSM service deleted.');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->groupRules());
        $group = $this->gsm->createGroup($validated);
        $this->gsm->attachGroupImage($group, $request->file('image'));

        return redirect()
            ->route('admin.gsm-tools.services', ['type' => $validated['service_type']])
            ->with('success', 'Category added.');
    }

    public function updateGroup(Request $request, GsmServiceGroup $gsmServiceGroup): RedirectResponse
    {
        $validated = $request->validate($this->groupRules());
        $group = $this->gsm->updateGroup($gsmServiceGroup, $validated);
        $this->gsm->attachGroupImage($group, $request->file('image'));

        return redirect()
            ->route('admin.gsm-tools.services', ['type' => $validated['service_type']])
            ->with('success', 'Category updated.');
    }

    public function destroyGroup(GsmServiceGroup $gsmServiceGroup): RedirectResponse
    {
        $type = ($gsmServiceGroup->service_type ?? GsmServiceType::Imei)->value;
        $this->gsm->deleteGroup($gsmServiceGroup);

        return redirect()
            ->route('admin.gsm-tools.services', ['type' => $type])
            ->with('success', 'Category deleted. Services in it are now uncategorized.');
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogServiceRules(): array
    {
        return [
            'gsm_service_group_id' => ['nullable', 'integer', Rule::exists('gsm_service_groups', 'id')],
            'overview' => ['nullable', 'string', 'max:8000'],
            'features' => ['nullable'],
            'what_to_send' => ['nullable', 'string', 'max:4000'],
            'eta_label' => ['nullable', 'string', 'max:40'],
            'allow_quantity' => ['nullable', 'boolean'],
            'min_qty' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'max_qty' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'image' => ['nullable', 'file', 'max:8192'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function groupRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'service_type' => ['required', Rule::enum(GsmServiceType::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'max:8192'],
        ];
    }
}
