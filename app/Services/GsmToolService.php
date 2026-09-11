<?php

namespace App\Services;

use App\Enums\GsmOrderStatus;
use App\Models\GsmOrder;
use App\Models\GsmOrderFieldValue;
use App\Models\GsmOrderStatusHistory;
use App\Models\GsmService;
use App\Models\GsmServiceField;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->with(['activeFields'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function servicePayload(GsmService $service): array
    {
        $service->loadMissing('activeFields');

        return [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'description' => $service->description,
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
     * @return array<string, mixed>
     */
    public function orderPayload(GsmOrder $order, bool $withHistory = false): array
    {
        $order->loadMissing(['fieldValues', 'service.activeFields', 'user:id,name,email,mobile']);

        $payload = [
            'id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'service_id' => $order->gsm_service_id,
            'service_name' => $order->service_name,
            'price_ghs' => (float) $order->price_ghs,
            'refunded' => (bool) $order->refunded,
            'admin_result_note' => $order->admin_result_note,
            'failure_reason' => $order->failure_reason,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'processing_at' => $order->processing_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'failed_at' => $order->failed_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'fields' => $order->fieldValues->map(fn (GsmOrderFieldValue $row) => [
                'name' => $row->field_name,
                'label' => $row->field_label,
                'value' => $row->value,
            ])->values()->all(),
            'can_cancel' => in_array($order->status, [GsmOrderStatus::Pending, GsmOrderStatus::Processing], true),
        ];

        if ($order->relationLoaded('user') && $order->user) {
            $payload['user'] = [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
                'mobile' => $order->user->mobile,
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
        $price = round((float) $service->price_ghs, 2);

        if ($price < 1) {
            throw ValidationException::withMessages([
                'gsm_service_id' => 'This service price is invalid.',
            ]);
        }

        return DB::transaction(function () use ($user, $request, $service, $fields, $values, $price) {
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

            $order = GsmOrder::create([
                'reference' => $this->nextReference(),
                'user_id' => $user->id,
                'gsm_service_id' => $service->id,
                'service_name' => $service->name,
                'price_ghs' => $price,
                'status' => GsmOrderStatus::Pending,
                'paid_at' => now(),
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
                'GSM-'.$order->id,
                'GSM Tools · '.$service->name.' ('.$order->reference.')',
            );

            $this->recordHistory($order, null, GsmOrderStatus::Pending, 'Paid from wallet — awaiting processing', $user->id);

            return $order->fresh(['fieldValues', 'service']);
        });
    }

    public function cancel(GsmOrder $order, User $actor, ?string $note = null): GsmOrder
    {
        if (! in_array($order->status, [GsmOrderStatus::Pending, GsmOrderStatus::Processing], true)) {
            throw ValidationException::withMessages(['status' => 'This order can no longer be cancelled.']);
        }

        return DB::transaction(function () use ($order, $actor, $note) {
            $this->refund($order, 'Order cancelled — funds returned to wallet');

            return $this->transition($order, GsmOrderStatus::Cancelled, $actor, $note ?: 'Cancelled', [
                'cancelled_at' => now(),
            ]);
        });
    }

    public function markProcessing(GsmOrder $order, User $admin, ?string $note = null): GsmOrder
    {
        if ($order->status !== GsmOrderStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Only pending orders can move to processing.']);
        }

        return $this->transition($order, GsmOrderStatus::Processing, $admin, $note ?: 'Processing started', [
            'processing_at' => now(),
            'assigned_admin_id' => $admin->id,
        ]);
    }

    public function complete(GsmOrder $order, User $admin, ?string $resultNote = null): GsmOrder
    {
        if (! in_array($order->status, [GsmOrderStatus::Pending, GsmOrderStatus::Processing], true)) {
            throw ValidationException::withMessages(['status' => 'This order cannot be completed.']);
        }

        return $this->transition($order, GsmOrderStatus::Completed, $admin, $resultNote ?: 'Completed', [
            'completed_at' => now(),
            'processing_at' => $order->processing_at ?? now(),
            'assigned_admin_id' => $admin->id,
            'admin_result_note' => $resultNote,
        ]);
    }

    public function fail(GsmOrder $order, User $admin, ?string $reason = null): GsmOrder
    {
        if (! in_array($order->status, [GsmOrderStatus::Pending, GsmOrderStatus::Processing], true)) {
            throw ValidationException::withMessages(['status' => 'This order cannot be failed.']);
        }

        return DB::transaction(function () use ($order, $admin, $reason) {
            $this->refund($order, 'Order failed — funds returned to wallet');

            return $this->transition($order, GsmOrderStatus::Failed, $admin, $reason ?: 'Failed', [
                'failed_at' => now(),
                'failure_reason' => $reason,
                'assigned_admin_id' => $admin->id,
            ]);
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

            $service = GsmService::create([
                'name' => $name,
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'price_ghs' => round((float) ($data['price_ghs'] ?? 0), 2),
                'currency' => 'GHS',
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'active' => (bool) ($data['active'] ?? true),
            ]);

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
            $service->save();

            if (is_array($fields)) {
                $this->syncFields($service, $fields);
            }

            return $service->fresh('fields');
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
            $rule = [$field->required ? 'required' : 'nullable', 'string', 'max:2000'];
            if ($field->type === 'url') {
                $rule[] = 'url';
            }
            if ($field->type === 'number') {
                $rule = [$field->required ? 'required' : 'nullable', 'numeric'];
            }
            $rules[$key] = $rule;
            $attributes[$key] = $field->label;
        }

        $validated = $request->validate($rules, [], $attributes);
        $input = $validated['fields'] ?? [];
        $out = [];

        foreach ($fields as $field) {
            $raw = $input[$field->name] ?? null;
            $out[$field->name] = is_scalar($raw) ? trim((string) $raw) : null;
        }

        return $out;
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
            'GSM-'.$order->id,
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

    private function nextReference(): string
    {
        do {
            $ref = 'GSM-'.strtoupper(Str::random(8));
        } while (GsmOrder::query()->where('reference', $ref)->exists());

        return $ref;
    }
}
