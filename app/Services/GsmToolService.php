<?php

namespace App\Services;

use App\Enums\GsmOrderStatus;
use App\Enums\GsmServiceType;
use App\Models\GsmOrder;
use App\Models\GsmOrderFieldValue;
use App\Models\GsmOrderReply;
use App\Models\GsmOrderStatusHistory;
use App\Models\GsmService;
use App\Models\GsmServiceGroup;
use App\Models\GsmServiceField;
use App\Models\User;
use App\Notifications\GsmOrderAdminNotification;
use App\Notifications\GsmOrderBuyerNotification;
use App\Support\PaymentReference;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GsmToolService
{
    public function pendingAdminCount(): int
    {
        return GsmOrder::query()
            ->whereIn('status', [GsmOrderStatus::Pending, GsmOrderStatus::Processing])
            ->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, GsmService>
     */
    public function activeServices()
    {
        return GsmService::query()
            ->where('active', true)
            ->with(['activeFields', 'group'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function servicePayload(GsmService $service): array
    {
        $service->loadMissing(['activeFields', 'group']);

        return [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'service_type' => ($service->service_type ?? GsmServiceType::Imei)->value,
            'service_type_label' => ($service->service_type ?? GsmServiceType::Imei)->label(),
            'group_id' => $service->gsm_service_group_id,
            'group_name' => $service->group?->name,
            'image_url' => $service->imageUrl(),
            'description' => $service->description,
            'overview' => $service->overview ?: $service->description,
            'features' => array_values(array_filter((array) ($service->features ?? []))),
            'what_to_send' => $service->what_to_send,
            'eta_label' => $service->eta_label ?: 'INSTANT',
            'allow_quantity' => (bool) $service->allow_quantity,
            'min_qty' => max(1, (int) ($service->min_qty ?: 1)),
            'max_qty' => max(1, (int) ($service->max_qty ?: 10)),
            'price_ghs' => (float) $service->price_ghs,
            'currency' => $service->currency ?: 'GHS',
            'sort_order' => (int) $service->sort_order,
            'active' => (bool) $service->active,
            'fields' => $service->activeFields->map(fn (GsmServiceField $field) => $this->fieldPayload($field))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function groupPayload(GsmServiceGroup $group, bool $withServices = false): array
    {
        $payload = [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'service_type' => ($group->service_type ?? GsmServiceType::Imei)->value,
            'image_url' => $group->imageUrl(),
            'sort_order' => (int) $group->sort_order,
            'active' => (bool) $group->active,
        ];
        if ($withServices) {
            $payload['services'] = $group->services
                ->where('active', true)
                ->map(fn (GsmService $service) => $this->servicePayload($service))
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalogGroups(?string $type = null): array
    {
        $query = GsmServiceGroup::query()->where('active', true)->with(['services.activeFields', 'services.group'])->orderBy('sort_order')->orderBy('name');
        if ($type) {
            $query->where('service_type', $type);
        }

        return $query->get()->map(fn (GsmServiceGroup $group) => $this->groupPayload($group, true))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldPayload(GsmServiceField $field): array
    {
        return [
            'id' => $field->id,
            'name' => $field->name,
            'label' => $field->label,
            'placeholder' => $field->placeholder,
            'type' => $field->type,
            'required' => (bool) $field->required,
            'sort_order' => (int) $field->sort_order,
            'active' => (bool) $field->active,
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>, paginator: \Illuminate\Contracts\Pagination\LengthAwarePaginator}
     */
    public function paginatedBuyerOrders(User $user, Request $request, int $defaultPerPage = 20): array
    {
        $perPage = min(max((int) $request->integer('per_page', $defaultPerPage), 1), 50);
        $page = GsmOrder::query()
            ->where('user_id', $user->id)
            ->with(['service', 'fieldValues', 'replies.admin:id,name'])
            ->latest()
            ->paginate($perPage);

        return [
            'data' => $page->getCollection()->map(fn (GsmOrder $order) => $this->orderPayload($order, buyerView: true))->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'has_more' => $page->hasMorePages(),
            ],
            'paginator' => $page,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderPayload(GsmOrder $order, bool $withHistory = false, bool $buyerView = false): array
    {
        $order->loadMissing(['fieldValues', 'service.fields', 'service.group', 'user:id,name,email,mobile', 'replies.admin:id,name']);
        $fieldsById = $order->service?->fields?->keyBy('id') ?? collect();
        $fieldsByName = $order->service?->fields?->keyBy('name') ?? collect();

        $payload = [
            'id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'status_tone' => $order->status->tone(),
            'service_id' => $order->gsm_service_id,
            'service_name' => $order->service_name,
            'image_url' => $order->service?->imageUrl(),
            'quantity' => max(1, (int) ($order->quantity ?: 1)),
            'unit_price_ghs' => (float) ($order->unit_price_ghs ?? $order->price_ghs),
            'contact_email' => null,
            'service_type' => ($order->service?->service_type ?? GsmServiceType::Imei)->value,
            'service_type_label' => ($order->service?->service_type ?? GsmServiceType::Imei)->label(),
            'eta_label' => $order->service?->eta_label ?: 'INSTANT',
            'price_ghs' => (float) $order->price_ghs,
            'refunded' => (bool) $order->refunded,
            'admin_result_note' => $order->admin_result_note,
            'failure_reason' => $order->failure_reason,
            'replies' => $order->replies->map(fn (GsmOrderReply $reply) => [
                'id' => $reply->id,
                'body' => $reply->body,
                'admin' => $reply->admin?->name ?: 'Admin',
                'created_at' => $reply->created_at?->toIso8601String(),
            ])->values()->all(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'processing_at' => $order->processing_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'failed_at' => $order->failed_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'fields' => $order->fieldValues->map(function (GsmOrderFieldValue $row) use ($fieldsById, $fieldsByName) {
                $definition = $fieldsById->get($row->gsm_service_field_id) ?? $fieldsByName->get($row->field_name);

                return [
                    'name' => $row->field_name,
                    'label' => $row->field_label,
                    'type' => $definition?->type ?? 'text',
                    'value' => $row->value,
                ];
            })->values()->all(),
            'can_cancel' => ! $buyerView && in_array($order->status, [GsmOrderStatus::Pending, GsmOrderStatus::Processing], true),
        ];

        if ($order->relationLoaded('user') && $order->user) {
            $payload['user'] = [
                'id' => $order->user->id,
                'name' => $order->user->name,
            ];
        }

        if ($withHistory) {
            $order->loadMissing('statusHistory.actor:id,name');
            $payload['history'] = $order->statusHistory->map(fn (GsmOrderStatusHistory $row) => [
                'from_status' => $row->from_status,
                'to_status' => $row->to_status,
                'note' => $row->note,
                'actor' => $row->actor?->name,
                'created_at' => $row->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $payload;
    }

    public function createOrder(User $user, Request $request): GsmOrder
    {
        $validated = $request->validate([
            'gsm_service_id' => ['required', 'integer', 'exists:gsm_services,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'email' => ['nullable', 'email', 'max:160'],
            'fields' => ['nullable', 'array'],
        ]);

        $service = GsmService::query()
            ->where('id', $validated['gsm_service_id'])
            ->where('active', true)
            ->with('activeFields')
            ->first();

        if (! $service) {
            throw ValidationException::withMessages([
                'gsm_service_id' => 'This GSM service is not available.',
            ]);
        }

        $fields = $service->activeFields;
        $values = $this->validatedFieldValues($request, $fields);
        $email = null;
        foreach ($fields as $field) {
            $isEmail = $field->type === 'email' || strcasecmp((string) $field->name, 'email') === 0 || strcasecmp((string) $field->label, 'email') === 0;
            if ($isEmail && filled($values[$field->name] ?? null)) {
                $email = trim((string) $values[$field->name]);
                break;
            }
        }
        $unit = round((float) $service->price_ghs, 2);
        $minQty = max(1, (int) ($service->min_qty ?: 1));
        $maxQty = max($minQty, (int) ($service->max_qty ?: 10));
        $quantity = $service->allow_quantity ? (int) ($validated['quantity'] ?? $minQty) : 1;
        $quantity = max($minQty, min($maxQty, $quantity));
        $price = round($unit * $quantity, 2);

        if ($unit < 1) {
            throw ValidationException::withMessages([
                'gsm_service_id' => 'This service price is invalid.',
            ]);
        }

        return DB::transaction(function () use ($user, $request, $service, $fields, $values, $price, $unit, $quantity, $email) {
            try {
                WalletService::ensure($user);
                WalletService::debitAvailable(
                    $user,
                    $price,
                    'You don\'t have enough balance. Please top up your wallet. You need GH₵'.number_format($price, 2).'.'
                );
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['balance' => $e->getMessage()]);
            }

            $values = $this->persistUploadedFields($values);

            $order = GsmOrder::create([
                'reference' => $this->nextReference(),
                'user_id' => $user->id,
                'gsm_service_id' => $service->id,
                'service_name' => $service->name,
                'quantity' => $quantity,
                'unit_price_ghs' => $unit,
                'contact_email' => $email,
                'price_ghs' => $price,
                'status' => GsmOrderStatus::Processing,
                'paid_at' => now(),
                'processing_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);

            foreach ($fields as $field) {
                GsmOrderFieldValue::create([
                    'gsm_order_id' => $order->id,
                    'gsm_service_field_id' => $field->id,
                    'field_name' => $field->name,
                    'field_label' => $field->label,
                    'value' => $values[$field->name] ?? null,
                ]);
            }

            WalletTransactionService::recordGsmToolsDebit(
                $user->id,
                $price,
                PaymentReference::gsm($order->id),
                'GSM Tools · '.$service->name.($quantity > 1 ? ' ×'.$quantity : '').' ('.$order->reference.')',
            );

            $this->recordHistory($order, null, GsmOrderStatus::Processing, 'Paid from wallet — Processing', $user->id);

            $fresh = $order->fresh(['fieldValues', 'service', 'user']);
            $this->notifyBuyer(
                $fresh,
                'GSM Tools order placed',
                $fresh->service_name.' is Processing.',
            );
            $this->notifyAdminsNewOrder($fresh);

            return $fresh;
        });
    }

    public function cancel(GsmOrder $order, User $actor, ?string $note = null): GsmOrder
    {
        if (! in_array($order->status, [GsmOrderStatus::Pending, GsmOrderStatus::Processing], true)) {
            throw ValidationException::withMessages(['status' => 'This order can no longer be cancelled.']);
        }

        return DB::transaction(function () use ($order, $actor, $note) {
            $this->refund($order, 'Order cancelled — funds returned to wallet');

            $updated = $this->transition($order, GsmOrderStatus::Cancelled, $actor, $note ?: 'Cancelled', [
                'cancelled_at' => now(),
            ]);
            $this->notifyBuyer(
                $updated,
                'GSM Tools cancelled',
                $updated->service_name.' was cancelled. Funds returned to your wallet.',
            );

            return $updated;
        });
    }

    public function markProcessing(GsmOrder $order, User $admin, ?string $note = null): GsmOrder
    {
        if ($order->status !== GsmOrderStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Only pending orders can move to processing.']);
        }

        $updated = $this->transition($order, GsmOrderStatus::Processing, $admin, $note ?: 'Processing started', [
            'processing_at' => now(),
            'assigned_admin_id' => $admin->id,
        ]);

        $this->notifyBuyer(
            $updated,
            'GSM Tools Processing',
            $updated->service_name.' ('.$updated->reference.') is now Processing.',
        );

        return $updated;
    }

    public function reply(GsmOrder $order, User $admin, string $message): GsmOrder
    {
        $body = trim($message);
        if ($body === '') {
            throw ValidationException::withMessages([
                'message' => 'Reply message cannot be empty.',
            ]);
        }

        if ($order->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'This order is already closed.',
            ]);
        }

        $fresh = DB::transaction(function () use ($order, $admin, $body) {
            if ($order->status === GsmOrderStatus::Pending) {
                $this->transition($order, GsmOrderStatus::Processing, $admin, 'Processing — admin replied', [
                    'processing_at' => now(),
                    'assigned_admin_id' => $admin->id,
                ]);
                $order->refresh();
            }

            $this->storeReply($order, $admin, $body);

            return $order->fresh(['fieldValues', 'service', 'user', 'replies.admin:id,name']);
        });

        $this->notifyBuyer(
            $fresh,
            'GSM Tools reply',
            Str::limit($body, 180),
        );

        return $fresh;
    }

    public function complete(GsmOrder $order, User $admin, ?string $resultNote = null): GsmOrder
    {
        if (! in_array($order->status, [GsmOrderStatus::Pending, GsmOrderStatus::Processing], true)) {
            throw ValidationException::withMessages(['status' => 'This order cannot be completed.']);
        }

        $note = trim((string) $resultNote);
        if ($note === '' && blank($order->admin_result_note) && $order->replies()->doesntExist()) {
            throw ValidationException::withMessages([
                'result_note' => 'Add a reply message for the buyer before completing.',
            ]);
        }

        $updated = DB::transaction(function () use ($order, $admin, $note) {
            if ($note !== '') {
                $this->storeReply($order, $admin, $note);
            }

            return $this->transition($order->fresh(), GsmOrderStatus::Completed, $admin, $note !== '' ? $note : 'Completed', [
                'completed_at' => now(),
                'processing_at' => $order->processing_at ?? now(),
                'assigned_admin_id' => $admin->id,
                'admin_result_note' => $note !== '' ? $note : $order->admin_result_note,
            ]);
        });

        $this->notifyBuyer(
            $updated,
            'GSM Tools Completed',
            $note !== ''
                ? Str::limit($note, 180)
                : $updated->service_name.' ('.$updated->reference.') is Completed.',
        );

        return $updated;
    }

    public function fail(GsmOrder $order, User $admin, ?string $reason = null): GsmOrder
    {
        if (! in_array($order->status, [GsmOrderStatus::Pending, GsmOrderStatus::Processing], true)) {
            throw ValidationException::withMessages(['status' => 'This order cannot be failed.']);
        }

        return DB::transaction(function () use ($order, $admin, $reason) {
            $this->refund($order, 'Order failed — funds returned to wallet');

            $updated = $this->transition($order, GsmOrderStatus::Failed, $admin, $reason ?: 'Failed', [
                'failed_at' => now(),
                'failure_reason' => $reason,
                'assigned_admin_id' => $admin->id,
            ]);
            $this->notifyBuyer(
                $updated,
                'GSM Tools failed',
                $reason ?: $updated->service_name.' failed. Funds returned to your wallet.',
            );

            return $updated;
        });
    }

    public function createGroup(array $data): GsmServiceGroup
    {
        $name = trim((string) ($data['name'] ?? ''));
        $slug = Str::slug((string) ($data['slug'] ?? $name));
        if ($slug === '') {
            $slug = 'gsm-cat-'.Str::lower(Str::random(6));
        }
        $base = $slug;
        $i = 1;
        while (GsmServiceGroup::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        $image = null;
        if (! empty($data['image']) && $data['image'] instanceof UploadedFile) {
            $image = $data['image']->store('gsm-groups', 'public');
        }

        return GsmServiceGroup::create([
            'name' => $name,
            'slug' => $slug,
            'service_type' => $this->serviceType($data['service_type'] ?? null),
            'image' => $image,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ]);
    }

    public function updateGroup(GsmServiceGroup $group, array $data): GsmServiceGroup
    {
        if (array_key_exists('name', $data)) {
            $group->name = trim((string) $data['name']);
        }
        if (array_key_exists('service_type', $data)) {
            $group->service_type = $this->serviceType($data['service_type']);
        }
        if (array_key_exists('sort_order', $data)) {
            $group->sort_order = (int) $data['sort_order'];
        }
        if (array_key_exists('active', $data)) {
            $group->active = (bool) $data['active'];
        }
        if (! empty($data['image']) && $data['image'] instanceof UploadedFile) {
            $group->image = $data['image']->store('gsm-groups', 'public');
        }
        $group->save();

        return $group->fresh();
    }

    public function deleteGroup(GsmServiceGroup $group): void
    {
        DB::transaction(function () use ($group) {
            $image = $group->image;
            $group->services()->update(['gsm_service_group_id' => null]);
            $group->delete();

            if (filled($image) && Storage::disk('public')->exists($image)) {
                Storage::disk('public')->delete($image);
            }
        });
    }

    /**
     * @param  array<int, array{label?: string, name?: string, placeholder?: string|null, type?: string, required?: bool}>  $fields
     */
    public function createService(array $data, array $fields = []): GsmService
    {
        return DB::transaction(function () use ($data, $fields) {
            $name = trim((string) ($data['name'] ?? ''));
            $slug = Str::slug((string) ($data['slug'] ?? $name));
            if ($slug === '') {
                $slug = 'gsm-'.Str::lower(Str::random(6));
            }

            $baseSlug = $slug;
            $i = 1;
            while (GsmService::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$i;
                $i++;
            }

            $service = GsmService::create(array_merge([
                'name' => $name,
                'slug' => $slug,
                'service_type' => $this->serviceType($data['service_type'] ?? null),
                'description' => $data['description'] ?? null,
                'price_ghs' => round((float) ($data['price_ghs'] ?? 0), 2),
                'currency' => 'GHS',
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'active' => (bool) ($data['active'] ?? true),
                'allow_quantity' => false,
                'min_qty' => 1,
                'max_qty' => 10,
            ], $this->catalogAttributes($data)));

            $this->syncFields($service, $fields);

            return $service->fresh('fields');
        });
    }

    /**
     * @param  array<int, array{id?: int, label?: string, name?: string, placeholder?: string|null, type?: string, required?: bool, active?: bool}>  $fields
     */
    public function updateService(GsmService $service, array $data, ?array $fields = null): GsmService
    {
        return DB::transaction(function () use ($service, $data, $fields) {
            if (array_key_exists('name', $data)) {
                $service->name = trim((string) $data['name']);
            }
            if (array_key_exists('service_type', $data)) {
                $service->service_type = $this->serviceType($data['service_type']);
            }
            if (array_key_exists('description', $data)) {
                $service->description = $data['description'];
            }
            if (array_key_exists('price_ghs', $data)) {
                $service->price_ghs = round((float) $data['price_ghs'], 2);
            }
            if (array_key_exists('sort_order', $data)) {
                $service->sort_order = (int) $data['sort_order'];
            }
            if (array_key_exists('active', $data)) {
                $service->active = (bool) $data['active'];
            }
            $service->fill($this->catalogAttributes($data));
            $service->save();

            if (is_array($fields)) {
                $this->syncFields($service, $fields);
            }

            return $service->fresh('fields');
        });
    }

    public function deleteService(GsmService $service): void
    {
        DB::transaction(function () use ($service) {
            $image = $service->image;
            $service->orders()->update(['gsm_service_id' => null]);
            $service->fields()->delete();
            $service->delete();

            if (filled($image) && Storage::disk('public')->exists($image)) {
                Storage::disk('public')->delete($image);
            }
        });
    }

    /**
     * @param  array<int, array{id?: int, label?: string, name?: string, placeholder?: string|null, type?: string, required?: bool, active?: bool}>  $fields
     */
    private function syncFields(GsmService $service, array $fields): void
    {
        $keepIds = [];
        $sort = 1;

        foreach ($fields as $row) {
            $label = trim((string) ($row['label'] ?? $row['name'] ?? ''));
            if ($label === '') {
                continue;
            }

            $name = Str::slug((string) ($row['name'] ?? $label), '_');
            if ($name === '') {
                $name = 'field_'.$sort;
            }

            $payload = [
                'name' => $name,
                'label' => $label,
                'placeholder' => $row['placeholder'] ?? null,
                'type' => in_array(($row['type'] ?? 'text'), GsmServiceField::TYPES, true) ? ($row['type'] ?? 'text') : 'text',
                'required' => array_key_exists('required', $row) ? (bool) $row['required'] : true,
                'sort_order' => $sort,
                'active' => array_key_exists('active', $row) ? (bool) $row['active'] : true,
            ];

            if (! empty($row['id'])) {
                $field = GsmServiceField::query()
                    ->where('gsm_service_id', $service->id)
                    ->where('id', $row['id'])
                    ->first();
                if ($field) {
                    $field->update($payload);
                    $keepIds[] = $field->id;
                    $sort++;
                    continue;
                }
            }

            $created = $service->fields()->create($payload);
            $keepIds[] = $created->id;
            $sort++;
        }

        $service->fields()
            ->when(count($keepIds) > 0, fn ($q) => $q->whereNotIn('id', $keepIds))
            ->when(count($keepIds) === 0, fn ($q) => $q)
            ->update(['active' => false]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, GsmServiceField>  $fields
     * @return array<string, string|null>
     */
    private function validatedFieldValues(Request $request, $fields): array
    {
        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            $key = 'fields.'.$field->name;
            if ($field->type === 'image') {
                $rule = [$field->required ? 'required' : 'nullable', 'image', 'max:8192'];
            } elseif ($field->type === 'number') {
                $rule = [$field->required ? 'required' : 'nullable', 'numeric'];
            } elseif ($field->type === 'email') {
                $rule = [$field->required ? 'required' : 'nullable', 'email', 'max:160'];
            } else {
                $rule = [$field->required ? 'required' : 'nullable', 'string', 'max:2000'];
                if ($field->type === 'url') {
                    $rule[] = 'url';
                }
            }
            $rules[$key] = $rule;
            $attributes[$key] = $field->label;
        }

        $request->validate($rules, [], $attributes);
        $out = [];

        foreach ($fields as $field) {
            if ($field->type === 'image') {
                $file = $request->file('fields.'.$field->name);
                $out[$field->name] = $file instanceof UploadedFile ? $file : null;
                continue;
            }

            $raw = $request->input('fields.'.$field->name);
            $out[$field->name] = is_scalar($raw) ? trim((string) $raw) : null;
        }

        return $out;
    }

    /**
     * @param  array<string, UploadedFile|string|null>  $values
     * @return array<string, string|null>
     */
    private function persistUploadedFields(array $values): array
    {
        foreach ($values as $name => $raw) {
            if (! $raw instanceof UploadedFile) {
                continue;
            }

            $path = $raw->store('gsm-orders', 'public');
            $values[$name] = url(Storage::disk('public')->url($path));
        }

        return $values;
    }

    private function serviceType(mixed $value): GsmServiceType
    {
        return GsmServiceType::tryFrom((string) $value) ?? GsmServiceType::Imei;
    }

    private function refund(GsmOrder $order, string $description): void
    {
        if ($order->refunded) {
            return;
        }

        $amount = round((float) $order->price_ghs, 2);
        if ($amount <= 0) {
            $order->refunded = true;
            $order->save();

            return;
        }

        $user = User::query()->findOrFail($order->user_id);
        $wallet = \App\Models\Wallet::where('user_id', $user->id)->lockForUpdate()->first()
            ?? WalletService::ensure($user);
        $wallet->increment('available_balance', $amount);

        WalletTransactionService::recordGsmToolsRefund(
            $user->id,
            $amount,
            PaymentReference::gsm((int) $order->id),
            $description.' ('.$order->reference.')',
        );

        $order->refunded = true;
        $order->save();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transition(
        GsmOrder $order,
        GsmOrderStatus $to,
        User $actor,
        string $note,
        array $extra = [],
    ): GsmOrder {
        return DB::transaction(function () use ($order, $to, $actor, $note, $extra) {
            $from = $order->status;
            $order->fill(array_merge(['status' => $to], $extra));
            $order->save();
            $this->recordHistory($order, $from, $to, $note, $actor->id);

            return $order->fresh(['fieldValues', 'service', 'user']);
        });
    }

    private function recordHistory(
        GsmOrder $order,
        ?GsmOrderStatus $from,
        GsmOrderStatus $to,
        string $note,
        ?int $actorId,
    ): void {
        GsmOrderStatusHistory::create([
            'gsm_order_id' => $order->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'actor_id' => $actorId,
        ]);
    }

    private function storeReply(GsmOrder $order, User $admin, string $body): GsmOrderReply
    {
        $reply = GsmOrderReply::create([
            'gsm_order_id' => $order->id,
            'admin_id' => $admin->id,
            'body' => $body,
        ]);

        $order->admin_result_note = $body;
        $order->assigned_admin_id = $admin->id;
        $order->save();

        return $reply;
    }

    private function notifyBuyer(GsmOrder $order, string $title, string $body): void
    {
        try {
            $user = $order->user ?? User::query()->find($order->user_id);
            if (! $user) {
                return;
            }

            AppNotificationService::send($user, 'gsm_tools', $title, $body, [
                'gsm_order_id' => $order->id,
                'path' => '/gsm-tools/orders/'.$order->id,
            ]);

            AdminNotifier::deliver($user, new GsmOrderBuyerNotification($order, $title, $body));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyAdminsNewOrder(GsmOrder $order): void
    {
        try {
            $body = $order->service_name.' · '.$order->reference.' · GH₵'.number_format((float) $order->price_ghs, 2);
            foreach (AdminNotifier::users() as $admin) {
                AppNotificationService::send($admin, 'gsm_tools', 'New GSM Tools order', $body, [
                    'gsm_order_id' => $order->id,
                    'path' => '/gsm-tools/'.$order->id,
                ]);
            }

            AdminNotifier::notify(new GsmOrderAdminNotification($order));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function attachServiceImage(GsmService $service, ?UploadedFile $file): GsmService
    {
        $path = $this->storePublicImage($file, 'gsm-services', $service->image);
        if ($path === null) {
            return $service;
        }

        $service->forceFill(['image' => $path])->save();

        return $service->fresh() ?? $service;
    }

    public function attachGroupImage(GsmServiceGroup $group, ?UploadedFile $file): GsmServiceGroup
    {
        $path = $this->storePublicImage($file, 'gsm-groups', $group->image);
        if ($path === null) {
            return $group;
        }

        $group->forceFill(['image' => $path])->save();

        return $group->fresh() ?? $group;
    }

    private function storePublicImage(?UploadedFile $file, string $directory, ?string $oldPath = null): ?string
    {
        if (! $file) {
            return null;
        }

        if (filled($oldPath) && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $ext = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif'], true)) {
            $ext = 'jpg';
        }

        return $file->storeAs($directory, Str::uuid()->toString().'.'.$ext, 'public');
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogAttributes(array $data): array
    {
        $attrs = [];
        if (array_key_exists('gsm_service_group_id', $data)) {
            $groupId = $data['gsm_service_group_id'] ? (int) $data['gsm_service_group_id'] : null;
            if ($groupId) {
                $group = GsmServiceGroup::query()->find($groupId);
                $attrs['gsm_service_group_id'] = $group?->id;
                if ($group && empty($data['service_type'])) {
                    $attrs['service_type'] = $group->service_type;
                }
            } else {
                $attrs['gsm_service_group_id'] = null;
            }
        }
        if (array_key_exists('overview', $data)) {
            $attrs['overview'] = $data['overview'] ?: null;
        }
        if (array_key_exists('what_to_send', $data)) {
            $attrs['what_to_send'] = $data['what_to_send'] ?: null;
        }
        if (array_key_exists('eta_label', $data)) {
            $attrs['eta_label'] = filled($data['eta_label'] ?? null) ? Str::limit((string) $data['eta_label'], 40, '') : 'INSTANT';
        }
        if (array_key_exists('allow_quantity', $data)) {
            $attrs['allow_quantity'] = filter_var($data['allow_quantity'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('min_qty', $data)) {
            $attrs['min_qty'] = max(1, (int) $data['min_qty']);
        }
        if (array_key_exists('max_qty', $data)) {
            $attrs['max_qty'] = max((int) ($attrs['min_qty'] ?? $data['min_qty'] ?? 1), (int) $data['max_qty']);
        }
        if (array_key_exists('features', $data)) {
            $attrs['features'] = $this->parseFeatures($data['features']);
        }
        if (! empty($data['image']) && $data['image'] instanceof UploadedFile) {
            $attrs['image'] = $data['image']->store('gsm-services', 'public');
        }

        return $attrs;
    }

    /**
     * @return list<string>
     */
    private function parseFeatures(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($row) => trim((string) $row), $value)));
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];

        return array_values(array_filter(array_map('trim', $lines)));
    }

    private function nextReference(): string
    {
        do {
            $ref = 'GSM-'.strtoupper(Str::random(8));
        } while (GsmOrder::query()->where('reference', $ref)->exists());

        return $ref;
    }
}
