<?php

namespace App\Services;

use App\Enums\SellRmbStatus;
use App\Models\SellRmbAdminNote;
use App\Models\SellRmbFieldValue;
use App\Models\SellRmbFormField;
use App\Models\SellRmbProof;
use App\Models\SellRmbRate;
use App\Models\SellRmbReceiveMethod;
use App\Models\SellRmbSetting;
use App\Models\SellRmbStatusHistory;
use App\Models\SellRmbTransfer;
use App\Models\User;
use App\Notifications\SellRmbAdminNotification;
use App\Notifications\SellRmbUserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SellRmbService
{
    public function settings(): SellRmbSetting
    {
        return SellRmbSetting::current();
    }

    public function currentRate(): ?SellRmbRate
    {
        $now = now();

        return SellRmbRate::query()
            ->where('active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->latest('id')
            ->first();
    }

    public function isOpen(): bool
    {
        $settings = $this->settings();

        return $settings->enabled
            && $this->currentRate() !== null
            && $this->hasReadyReceiveMethod();
    }

    public function hasReadyReceiveMethod(): bool
    {
        return SellRmbReceiveMethod::query()
            ->where('active', true)
            ->where(function ($q) {
                $q->whereIn('type', ['bank', 'other'])
                    ->orWhereNotNull('qr_path');
            })
            ->exists();
    }

    public function replaceMethodQr(SellRmbReceiveMethod $method, UploadedFile $file): SellRmbReceiveMethod
    {
        if ($method->qr_path) {
            Storage::disk('public')->delete($method->qr_path);
        }

        $method->update([
            'qr_path' => $file->store('sell-rmb/methods', 'public'),
            'active' => true,
        ]);

        return $method->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function quote(float $rmbAmount, string $payoutCurrency = 'ghs', ?SellRmbRate $rate = null): array
    {
        $rate ??= $this->currentRate();
        if (! $rate) {
            throw ValidationException::withMessages([
                'rmb_amount' => 'Sell RMB is not open. Admin has not published a buying rate yet.',
            ]);
        }

        $payoutCurrency = strtolower($payoutCurrency);
        if (! in_array($payoutCurrency, ['usd', 'ghs'], true)) {
            throw ValidationException::withMessages([
                'payout_currency' => 'Choose USD or GHS payout.',
            ]);
        }

        $rmb = round($rmbAmount, 2);
        $usdPerRmb = (float) $rate->usd_per_rmb;
        $ghsPerUsd = (float) $rate->ghs_per_usd;
        $ghsPerRmb = $rate->ghsPerRmb();

        if ($usdPerRmb <= 0 || $ghsPerUsd <= 0 || $ghsPerRmb <= 0) {
            throw ValidationException::withMessages(['rmb_amount' => 'The published buying rate is invalid.']);
        }

        $usdGross = round($rmb * $usdPerRmb, 2);
        $feeUsd = $rate->fee_mode === 'percent'
            ? round($usdGross * ((float) $rate->fee_value) / 100, 2)
            : round((float) $rate->fee_value, 2);
        $usdPayout = round($usdGross - $feeUsd, 2);
        // rmb-wallet style: GHS payout is RMB × (GHS per RMB), after the same fee share.
        $ghsGross = round($rmb * $ghsPerRmb, 2);
        $feeGhs = $usdGross > 0 ? round($ghsGross * ($feeUsd / $usdGross), 2) : 0.0;
        $ghsPayout = round($ghsGross - $feeGhs, 2);

        if ($usdPayout < 0 || $ghsPayout < 0) {
            throw ValidationException::withMessages(['rmb_amount' => 'Fee exceeds the estimated payout.']);
        }

        return [
            'rmb_amount' => $rmb,
            'usd_per_rmb' => $usdPerRmb,
            'ghs_per_usd' => $ghsPerUsd,
            'ghs_per_rmb' => $ghsPerRmb,
            'usd_gross' => $usdGross,
            'fee_mode' => $rate->fee_mode,
            'fee_value' => (float) $rate->fee_value,
            'fee_usd' => $feeUsd,
            'usd_payout' => $usdPayout,
            'ghs_payout' => $ghsPayout,
            'payout_currency' => $payoutCurrency,
            'payout_amount' => $payoutCurrency === 'ghs' ? $ghsPayout : $usdPayout,
            'rate_id' => $rate->id,
            'min_rmb' => (float) $rate->min_rmb,
            'max_rmb' => (float) $rate->max_rmb,
            'breakdown' => [
                'rmb' => '¥'.number_format($rmb, 2),
                'rate' => '1 RMB = GH₵'.number_format($ghsPerRmb, 4),
                'usd_gross' => '$'.number_format($usdGross, 2),
                'fee' => '$'.number_format($feeUsd, 2),
                'usd_payout' => '$'.number_format($usdPayout, 2),
                'ghs_payout' => 'GH₵'.number_format($ghsPayout, 2),
                'ghs_per_usd' => '1 USD = GH₵'.number_format($ghsPerUsd, 4),
            ],
        ];
    }

    public function create(User $user, Request $request): SellRmbTransfer
    {
        if (! $this->isOpen()) {
            throw ValidationException::withMessages([
                'rmb_amount' => 'Sell RMB is not available right now.',
            ]);
        }

        $rate = $this->currentRate();
        $validated = $request->validate([
            'rmb_amount' => ['required', 'numeric', 'min:1'],
            // Sell RMB for GHS (rmb-wallet style). USD kept for older clients.
            'payout_currency' => ['nullable', 'in:usd,ghs'],
            'receive_method_id' => ['required', 'integer', 'exists:sell_rmb_receive_methods,id'],
        ]);

        $method = SellRmbReceiveMethod::query()
            ->where('id', $validated['receive_method_id'])
            ->where('active', true)
            ->first();

        if (! $method) {
            throw ValidationException::withMessages([
                'receive_method_id' => 'Choose an active receive method.',
            ]);
        }

        $payoutCurrency = $validated['payout_currency'] ?? 'ghs';
        $quote = $this->quote((float) $validated['rmb_amount'], $payoutCurrency, $rate);
        $this->assertLimits($user, $quote, $rate);

        $fields = $this->activeFields();
        $this->validateFields($request, $fields, $method);

        return DB::transaction(function () use ($user, $request, $quote, $rate, $method, $fields) {
            // External sell: buyer sends RMB to Alipay; CityShop pays MoMo/GHS out.
            $status = SellRmbStatus::Submitted;

            $needsApproval = $rate->approval_above_rmb !== null
                && $quote['rmb_amount'] >= (float) $rate->approval_above_rmb;

            $transfer = SellRmbTransfer::create([
                'reference' => $this->nextReference(),
                'user_id' => $user->id,
                'status' => $status,
                'rmb_amount' => $quote['rmb_amount'],
                'usd_per_rmb' => $quote['usd_per_rmb'],
                'ghs_per_usd' => $quote['ghs_per_usd'],
                'fee_mode' => $quote['fee_mode'],
                'fee_value' => $quote['fee_value'],
                'fee_usd' => $quote['fee_usd'],
                'usd_payout' => $quote['usd_payout'],
                'ghs_payout' => $quote['ghs_payout'],
                'payout_currency' => $quote['payout_currency'],
                'rate_id' => $rate->id,
                'receive_method_id' => $method->id,
                'payment_reference' => $this->fieldText($request, $fields, 'payment_reference'),
                'needs_approval' => $needsApproval,
                'submitted_at' => now(),
            ]);

            $this->storeFieldValues($transfer, $request, $fields);
            $this->recordHistory($transfer, null, $status, 'Sell RMB submitted — awaiting admin verification', $user->id);
            $this->notifyUser($transfer, $status);
            $this->notifyAdmins($transfer, $status);

            return $transfer->fresh([
                'fieldValues.field',
                'proofs',
                'statusHistory',
                'receiveMethod',
            ]);
        });
    }

    public function cancel(SellRmbTransfer $transfer, User $actor, ?string $note = null): SellRmbTransfer
    {
        $this->assertMutable($transfer);

        if ($actor->id === $transfer->user_id && ! in_array($transfer->status, [
            SellRmbStatus::Submitted,
            SellRmbStatus::RmbVerification,
            SellRmbStatus::RmbReceived,
            SellRmbStatus::PayoutProcessing,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'You can only cancel while the request is still processing.',
            ]);
        }

        if (! in_array($transfer->status, [
            SellRmbStatus::Submitted,
            SellRmbStatus::RmbVerification,
            SellRmbStatus::RmbReceived,
            SellRmbStatus::PayoutProcessing,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'This Sell RMB request cannot be cancelled.']);
        }

        return $this->transition($transfer, SellRmbStatus::Cancelled, $actor, $note ?: 'Cancelled', [
            'cancelled_at' => now(),
        ]);
    }

    public function startVerification(SellRmbTransfer $transfer, User $admin): SellRmbTransfer
    {
        $this->assertAdminAction($transfer, [SellRmbStatus::Submitted]);

        return $this->transition($transfer, SellRmbStatus::RmbVerification, $admin, 'RMB verification started', [
            'verified_at' => now(),
            'assigned_admin_id' => $admin->id,
        ]);
    }

    public function markRmbReceived(SellRmbTransfer $transfer, User $admin): SellRmbTransfer
    {
        $this->assertAdminAction($transfer, [SellRmbStatus::RmbVerification]);

        return $this->transition($transfer, SellRmbStatus::RmbReceived, $admin, 'RMB received', [
            'rmb_received_at' => now(),
            'assigned_admin_id' => $admin->id,
            'verified_at' => $transfer->verified_at ?? now(),
        ]);
    }

    public function startPayoutProcessing(SellRmbTransfer $transfer, User $admin): SellRmbTransfer
    {
        $this->assertAdminAction($transfer, [SellRmbStatus::RmbReceived]);

        return $this->transition($transfer, SellRmbStatus::PayoutProcessing, $admin, 'Payout processing', [
            'payout_processing_at' => now(),
            'assigned_admin_id' => $admin->id,
        ]);
    }

    public function markPaid(SellRmbTransfer $transfer, User $admin, Request $request): SellRmbTransfer
    {
        $this->assertAdminAction($transfer, [SellRmbStatus::PayoutProcessing]);

        $validated = $request->validate([
            'payout_amount' => ['nullable', 'numeric', 'min:0.01'],
            'payout_ref' => ['nullable', 'string', 'max:120'],
            'payout_channel' => ['nullable', 'string', 'max:80'],
            'payout_paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'proof' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        $payoutAmount = isset($validated['payout_amount']) && (float) $validated['payout_amount'] > 0
            ? (float) $validated['payout_amount']
            : $transfer->expectedPayoutAmount();

        if ($payoutAmount <= 0) {
            throw ValidationException::withMessages([
                'payout_amount' => 'This transfer has no payout amount.',
            ]);
        }

        $file = $request->file('proof');
        $path = $file->store('sell-rmb/'.$transfer->id.'/payout-proof', 'public');

        SellRmbProof::create([
            'sell_rmb_transfer_id' => $transfer->id,
            'type' => 'payout_sent',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'note' => $validated['note'] ?? null,
            'uploaded_by' => $admin->id,
        ]);

        return $this->transition($transfer, SellRmbStatus::Paid, $admin, $validated['note'] ?? 'Payout sent', [
            'payout_amount' => round($payoutAmount, 2),
            'payout_ref' => $validated['payout_ref'] ?? null,
            'payout_channel' => $validated['payout_channel'] ?? null,
            'payout_paid_at' => $validated['payout_paid_at'] ?? now(),
            'paid_at' => now(),
            'assigned_admin_id' => $admin->id,
        ]);
    }

    public function complete(SellRmbTransfer $transfer, User $admin): SellRmbTransfer
    {
        $this->assertAdminAction($transfer, [SellRmbStatus::Paid]);

        if (! $transfer->proofs()->where('type', 'payout_sent')->exists()) {
            throw ValidationException::withMessages([
                'proof' => 'Upload payout proof before completing.',
            ]);
        }

        return $this->transition($transfer, SellRmbStatus::Completed, $admin, 'Completed', [
            'completed_at' => now(),
            'assigned_admin_id' => $admin->id,
        ]);
    }

    public function reject(SellRmbTransfer $transfer, User $admin, string $reason): SellRmbTransfer
    {
        $this->assertAdminAction($transfer, [
            SellRmbStatus::Submitted,
            SellRmbStatus::RmbVerification,
            SellRmbStatus::PayoutProcessing,
        ]);

        return $this->transition($transfer, SellRmbStatus::Rejected, $admin, $reason, [
            'rejection_reason' => $reason,
            'assigned_admin_id' => $admin->id,
        ]);
    }

    public function fail(SellRmbTransfer $transfer, User $admin, string $reason): SellRmbTransfer
    {
        $this->assertAdminAction($transfer, [
            SellRmbStatus::RmbReceived,
            SellRmbStatus::PayoutProcessing,
            SellRmbStatus::Paid,
        ]);

        return $this->transition($transfer, SellRmbStatus::Failed, $admin, $reason, [
            'rejection_reason' => $reason,
        ]);
    }

    public function addNote(SellRmbTransfer $transfer, User $admin, string $note): SellRmbAdminNote
    {
        return SellRmbAdminNote::create([
            'sell_rmb_transfer_id' => $transfer->id,
            'admin_id' => $admin->id,
            'note' => $note,
        ]);
    }

    public function publishRate(User $admin, array $data): SellRmbRate
    {
        return DB::transaction(function () use ($admin, $data) {
            SellRmbRate::query()
                ->where('active', true)
                ->whereNull('effective_to')
                ->update([
                    'active' => false,
                    'effective_to' => now(),
                ]);

            // rmb-wallet style: admin sets 1 RMB = X GHS.
            // Stored as usd_per_rmb=1 and ghs_per_usd=X so existing columns keep working.
            if (isset($data['ghs_per_rmb']) && (float) $data['ghs_per_rmb'] > 0) {
                $usdPerRmb = 1.0;
                $ghsPerUsd = (float) $data['ghs_per_rmb'];
            } else {
                $usdPerRmb = (float) ($data['usd_per_rmb'] ?? 0);
                $ghsPerUsd = (float) ($data['ghs_per_usd'] ?? 0);
            }

            return SellRmbRate::create([
                'usd_per_rmb' => $usdPerRmb,
                'ghs_per_usd' => $ghsPerUsd,
                'fee_mode' => $data['fee_mode'] ?? 'flat',
                'fee_value' => $data['fee_value'] ?? 0,
                'min_rmb' => $data['min_rmb'] ?? 100,
                'max_rmb' => $data['max_rmb'] ?? 50000,
                'daily_max_rmb' => $data['daily_max_rmb'] ?? null,
                'monthly_max_rmb' => $data['monthly_max_rmb'] ?? null,
                'max_per_day' => $data['max_per_day'] ?? null,
                'approval_above_rmb' => $data['approval_above_rmb'] ?? null,
                'active' => true,
                'effective_from' => $data['effective_from'] ?? now(),
                'created_by' => $admin->id,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function configPayload(): array
    {
        $settings = $this->settings();
        $rate = $this->currentRate();
        $quote = $rate ? $this->quote((float) $rate->min_rmb, 'ghs', $rate) : null;

        return [
            'enabled' => $this->isOpen(),
            'instructions' => $settings->instructions,
            'receive_instructions' => $settings->receive_instructions,
            'rate' => $rate ? $this->ratePayload($rate) : null,
            'sample_quote' => $quote,
            'default_payout_currency' => 'ghs',
            'receive_methods' => SellRmbReceiveMethod::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SellRmbReceiveMethod $m) => $this->methodPayload($m))
                ->values()
                ->all(),
            'fields' => $this->activeFields()
                ->map(fn (SellRmbFormField $f) => $this->fieldPayload($f))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transferPayload(SellRmbTransfer $transfer, bool $forAdmin = false): array
    {
        $transfer->loadMissing([
            'user:id,name,email,mobile',
            'receiveMethod',
            'fieldValues.field',
            'proofs',
            'statusHistory.actor:id,name',
            'adminNotes.admin:id,name',
            'assignedAdmin:id,name',
        ]);

        $quote = [
            'rmb_amount' => (float) $transfer->rmb_amount,
            'usd_per_rmb' => (float) $transfer->usd_per_rmb,
            'ghs_per_usd' => (float) $transfer->ghs_per_usd,
            'ghs_per_rmb' => round((float) $transfer->usd_per_rmb * (float) $transfer->ghs_per_usd, 6),
            'fee_mode' => $transfer->fee_mode,
            'fee_value' => (float) $transfer->fee_value,
            'fee_usd' => (float) $transfer->fee_usd,
            'usd_payout' => (float) $transfer->usd_payout,
            'ghs_payout' => (float) $transfer->ghs_payout,
            'payout_currency' => $transfer->payout_currency,
            'payout_amount' => $transfer->expectedPayoutAmount(),
            'breakdown' => [
                'rmb' => '¥'.number_format((float) $transfer->rmb_amount, 2),
                'rate' => '1 RMB = GH₵'.number_format((float) $transfer->usd_per_rmb * (float) $transfer->ghs_per_usd, 4),
                'fee' => '$'.number_format((float) $transfer->fee_usd, 2),
                'usd_payout' => '$'.number_format((float) $transfer->usd_payout, 2),
                'ghs_payout' => 'GH₵'.number_format((float) $transfer->ghs_payout, 2),
                'ghs_per_usd' => '1 USD = GH₵'.number_format((float) $transfer->ghs_per_usd, 4),
            ],
        ];

        $payload = [
            'id' => $transfer->id,
            'reference' => $transfer->reference,
            'status' => $transfer->status->value,
            'status_label' => $transfer->status->label(),
            'quote' => $quote,
            'needs_approval' => $transfer->needs_approval,
            'receive_method' => $transfer->receiveMethod ? $this->methodPayload($transfer->receiveMethod) : null,
            'payment_reference' => $transfer->payment_reference,
            'payment_proof_url' => $transfer->paymentProofUrl(),
            'rejection_reason' => $transfer->rejection_reason,
            'payout_amount' => $transfer->payout_amount !== null ? (float) $transfer->payout_amount : null,
            'payout_ref' => $transfer->payout_ref,
            'payout_channel' => $transfer->payout_channel,
            'payout_paid_at' => $transfer->payout_paid_at?->toIso8601String(),
            'created_at' => $transfer->created_at?->toIso8601String(),
            'submitted_at' => $transfer->submitted_at?->toIso8601String(),
            'verified_at' => $transfer->verified_at?->toIso8601String(),
            'rmb_received_at' => $transfer->rmb_received_at?->toIso8601String(),
            'payout_processing_at' => $transfer->payout_processing_at?->toIso8601String(),
            'paid_at' => $transfer->paid_at?->toIso8601String(),
            'completed_at' => $transfer->completed_at?->toIso8601String(),
            'cancelled_at' => $transfer->cancelled_at?->toIso8601String(),
            'can_cancel' => in_array($transfer->status, [
                SellRmbStatus::Submitted,
                SellRmbStatus::RmbVerification,
                SellRmbStatus::RmbReceived,
                SellRmbStatus::PayoutProcessing,
            ], true),
            'can_verify' => $transfer->status === SellRmbStatus::Submitted,
            'can_mark_received' => $transfer->status === SellRmbStatus::RmbVerification,
            'can_start_payout' => $transfer->status === SellRmbStatus::RmbReceived,
            'can_mark_paid' => $transfer->status === SellRmbStatus::PayoutProcessing,
            'can_complete' => $transfer->status === SellRmbStatus::Paid,
            'timeline' => $this->timelinePayload($transfer),
            'fields' => $transfer->fieldValues->map(fn (SellRmbFieldValue $v) => [
                'id' => $v->id,
                'field_id' => $v->field_id,
                'name' => $v->field?->name,
                'label' => $v->field?->label,
                'group' => $v->field?->group,
                'type' => $v->field?->type,
                'value' => $v->value,
                'file_url' => $v->fileUrl(),
            ])->values()->all(),
            'proofs' => $transfer->proofs->map(fn (SellRmbProof $p) => [
                'id' => $p->id,
                'type' => $p->type,
                'url' => $p->url(),
                'original_name' => $p->original_name,
                'mime' => $p->mime,
                'note' => $p->note,
                'created_at' => $p->created_at?->toIso8601String(),
            ])->values()->all(),
            'history' => $transfer->statusHistory->map(fn (SellRmbStatusHistory $h) => [
                'from' => $h->from_status?->value,
                'to' => $h->to_status->value,
                'to_label' => $h->to_status->label(),
                'note' => $h->note,
                'actor' => $h->actor?->name,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values()->all(),
        ];

        if ($forAdmin) {
            $payload['user'] = $transfer->user ? [
                'id' => $transfer->user->id,
                'name' => $transfer->user->name,
                'email' => $transfer->user->email,
                'mobile' => $transfer->user->mobile,
            ] : null;
            $payload['assigned_admin'] = $transfer->assignedAdmin?->name;
            $payload['admin_notes'] = $transfer->adminNotes->map(fn (SellRmbAdminNote $n) => [
                'id' => $n->id,
                'note' => $n->note,
                'admin' => $n->admin?->name,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values()->all();
            $payload['flow'] = 'sell_rmb';
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $base = SellRmbTransfer::query();

        return [
            'total' => (clone $base)->count(),
            'submitted' => (clone $base)->where('status', SellRmbStatus::Submitted)->count(),
            'awaiting_verification' => (clone $base)->whereIn('status', [
                SellRmbStatus::Submitted,
                SellRmbStatus::RmbVerification,
            ])->count(),
            'processing' => (clone $base)->whereIn('status', [
                SellRmbStatus::RmbReceived,
                SellRmbStatus::PayoutProcessing,
                SellRmbStatus::Paid,
            ])->count(),
            'completed' => (clone $base)->where('status', SellRmbStatus::Completed)->count(),
            'failed' => (clone $base)->whereIn('status', [
                SellRmbStatus::Rejected,
                SellRmbStatus::Cancelled,
                SellRmbStatus::Failed,
            ])->count(),
            'rmb_received' => (float) (clone $base)->where('status', SellRmbStatus::Completed)->sum('rmb_amount'),
            'usd_paid' => (float) (clone $base)->where('status', SellRmbStatus::Completed)->where('payout_currency', 'usd')->sum('payout_amount'),
            'ghs_paid' => (float) (clone $base)->where('status', SellRmbStatus::Completed)->where('payout_currency', 'ghs')->sum('payout_amount'),
            'fees_collected' => (float) (clone $base)->where('status', SellRmbStatus::Completed)->sum('fee_usd'),
            'today' => (clone $base)->whereDate('created_at', today())->count(),
            'this_month' => (clone $base)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];
    }

    public function pendingAdminCount(): int
    {
        return SellRmbTransfer::query()
            ->whereIn('status', [
                SellRmbStatus::Submitted,
                SellRmbStatus::RmbVerification,
                SellRmbStatus::RmbReceived,
                SellRmbStatus::PayoutProcessing,
                SellRmbStatus::Paid,
            ])
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function ratePayload(SellRmbRate $rate): array
    {
        return [
            'id' => $rate->id,
            'usd_per_rmb' => (float) $rate->usd_per_rmb,
            'ghs_per_usd' => (float) $rate->ghs_per_usd,
            'ghs_per_rmb' => $rate->ghsPerRmb(),
            'fee_mode' => $rate->fee_mode,
            'fee_value' => (float) $rate->fee_value,
            'min_rmb' => (float) $rate->min_rmb,
            'max_rmb' => (float) $rate->max_rmb,
            'daily_max_rmb' => $rate->daily_max_rmb !== null ? (float) $rate->daily_max_rmb : null,
            'monthly_max_rmb' => $rate->monthly_max_rmb !== null ? (float) $rate->monthly_max_rmb : null,
            'max_per_day' => $rate->max_per_day,
            'approval_above_rmb' => $rate->approval_above_rmb !== null ? (float) $rate->approval_above_rmb : null,
            'active' => $rate->active,
            'effective_from' => $rate->effective_from?->toIso8601String(),
            'effective_to' => $rate->effective_to?->toIso8601String(),
            'updated_at' => $rate->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function methodPayload(SellRmbReceiveMethod $method): array
    {
        return [
            'id' => $method->id,
            'name' => $method->name,
            'type' => $method->type,
            'account_name' => $method->account_name,
            'account_number' => $method->account_number,
            'network' => $method->network,
            'instructions' => $method->instructions,
            'qr_url' => $method->qrUrl(),
            'qr_updated_at' => $method->updated_at?->toIso8601String(),
            'proof_required' => $method->proof_required,
            'sort_order' => $method->sort_order,
            'active' => $method->active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldPayload(SellRmbFormField $field): array
    {
        return [
            'id' => $field->id,
            'group' => $field->group,
            'type' => $field->type,
            'name' => $field->name,
            'label' => $field->label,
            'placeholder' => $field->placeholder,
            'help_text' => $field->help_text,
            'required' => $field->required,
            'options' => $field->options ?? [],
            'file_types' => $field->file_types ?? [],
            'max_size_kb' => $field->max_size_kb,
            'sort_order' => $field->sort_order,
            'active' => $field->active,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, SellRmbFormField>
     */
    public function activeFields()
    {
        return SellRmbFormField::query()
            ->where('active', true)
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @param  list<SellRmbStatus>  $allowed
     */
    private function assertAdminAction(SellRmbTransfer $transfer, array $allowed): void
    {
        $this->assertMutable($transfer);

        if (! in_array($transfer->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'This action is not available for '.$transfer->status->label().'.',
            ]);
        }
    }

    private function assertMutable(SellRmbTransfer $transfer): void
    {
        if ($transfer->status->isImmutable()) {
            throw ValidationException::withMessages([
                'status' => 'Completed Sell RMB requests cannot be edited.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function assertLimits(User $user, array $quote, SellRmbRate $rate): void
    {
        $rmb = $quote['rmb_amount'];

        if ($rmb < (float) $rate->min_rmb) {
            throw ValidationException::withMessages([
                'rmb_amount' => 'Minimum amount is ¥'.number_format((float) $rate->min_rmb, 2).'.',
            ]);
        }

        if ($rmb > (float) $rate->max_rmb) {
            throw ValidationException::withMessages([
                'rmb_amount' => 'Maximum per request is ¥'.number_format((float) $rate->max_rmb, 2).'.',
            ]);
        }

        $openStatuses = [
            SellRmbStatus::Submitted,
            SellRmbStatus::RmbVerification,
            SellRmbStatus::RmbReceived,
            SellRmbStatus::PayoutProcessing,
            SellRmbStatus::Paid,
            SellRmbStatus::Completed,
        ];

        if ($rate->max_per_day) {
            $todayCount = SellRmbTransfer::query()
                ->where('user_id', $user->id)
                ->whereDate('created_at', today())
                ->whereNotIn('status', [
                    SellRmbStatus::Cancelled,
                    SellRmbStatus::Rejected,
                    SellRmbStatus::Failed,
                ])
                ->count();

            if ($todayCount >= $rate->max_per_day) {
                throw ValidationException::withMessages([
                    'rmb_amount' => 'You have reached the daily Sell RMB limit.',
                ]);
            }
        }

        if ($rate->daily_max_rmb) {
            $todaySum = (float) SellRmbTransfer::query()
                ->where('user_id', $user->id)
                ->whereDate('created_at', today())
                ->whereIn('status', $openStatuses)
                ->sum('rmb_amount');

            if ($todaySum + $rmb > (float) $rate->daily_max_rmb) {
                throw ValidationException::withMessages([
                    'rmb_amount' => 'This amount exceeds your daily Sell RMB limit.',
                ]);
            }
        }

        if ($rate->monthly_max_rmb) {
            $monthSum = (float) SellRmbTransfer::query()
                ->where('user_id', $user->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->whereIn('status', $openStatuses)
                ->sum('rmb_amount');

            if ($monthSum + $rmb > (float) $rate->monthly_max_rmb) {
                throw ValidationException::withMessages([
                    'rmb_amount' => 'This amount exceeds your monthly Sell RMB limit.',
                ]);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SellRmbFormField>  $fields
     */
    private function validateFields(Request $request, $fields, SellRmbReceiveMethod $method): void
    {
        $rules = [];

        foreach ($fields as $field) {
            $key = $field->isFile() ? 'files.'.$field->id : 'fields.'.$field->id;
            $required = $field->required ? 'required' : 'nullable';

            if ($field->name === 'payment_screenshot' && $method->proof_required) {
                $required = 'required';
            }

            $rules[$key] = match ($field->type) {
                'number' => [$required, 'numeric'],
                'email' => [$required, 'email', 'max:190'],
                'phone' => [$required, 'string', 'max:40'],
                'textarea' => [$required, 'string', 'max:2000'],
                'date' => [$required, 'date'],
                'dropdown', 'radio' => array_filter([$required, 'string', $field->options ? 'in:'.implode(',', $field->options) : null]),
                'checkbox' => ['sometimes'],
                'image' => [$required, 'image', 'max:'.($field->max_size_kb ?: 5120)],
                'document' => [$required, 'file', 'max:'.($field->max_size_kb ?: 8192), 'mimes:pdf,jpg,jpeg,png,webp'],
                'files' => [$required, 'array'],
                default => [$required, 'string', 'max:255'],
            };

            if ($field->type === 'files') {
                $rules[$key.'.*'] = ['file', 'max:'.($field->max_size_kb ?: 5120)];
            }
        }

        $request->validate($rules);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SellRmbFormField>  $fields
     */
    private function fieldText(Request $request, $fields, string $name): ?string
    {
        $field = $fields->firstWhere('name', $name);
        if (! $field) {
            return null;
        }

        $value = $request->input('fields.'.$field->id);

        return filled($value) ? trim((string) $value) : null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SellRmbFormField>  $fields
     */
    private function storeFieldValues(SellRmbTransfer $transfer, Request $request, $fields): void
    {
        foreach ($fields as $field) {
            if ($field->isFile()) {
                $uploaded = $request->file('files.'.$field->id);
                if ($uploaded instanceof UploadedFile) {
                    $path = $uploaded->store('sell-rmb/'.$transfer->id.'/fields', 'public');
                    SellRmbFieldValue::create([
                        'sell_rmb_transfer_id' => $transfer->id,
                        'field_id' => $field->id,
                        'file_path' => $path,
                    ]);
                    if ($field->name === 'payment_screenshot') {
                        $transfer->update(['payment_proof_path' => $path]);
                    }
                } elseif (is_array($uploaded)) {
                    $paths = [];
                    foreach ($uploaded as $file) {
                        if ($file instanceof UploadedFile) {
                            $paths[] = $file->store('sell-rmb/'.$transfer->id.'/fields', 'public');
                        }
                    }
                    if ($paths !== []) {
                        SellRmbFieldValue::create([
                            'sell_rmb_transfer_id' => $transfer->id,
                            'field_id' => $field->id,
                            'value' => json_encode($paths),
                            'file_path' => $paths[0],
                        ]);
                    }
                }

                continue;
            }

            $value = $request->input('fields.'.$field->id);
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $value = json_encode($value);
            }

            SellRmbFieldValue::create([
                'sell_rmb_transfer_id' => $transfer->id,
                'field_id' => $field->id,
                'value' => (string) $value,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(
        SellRmbTransfer $transfer,
        SellRmbStatus $to,
        User $actor,
        ?string $note = null,
        array $attributes = [],
    ): SellRmbTransfer {
        $from = $transfer->status;

        $transfer->fill($attributes);
        $transfer->status = $to;
        $transfer->save();

        $this->recordHistory($transfer, $from, $to, $note, $actor->id);
        $this->notifyUser($transfer->fresh(), $to);

        return $transfer->fresh([
            'fieldValues.field',
            'proofs',
            'statusHistory',
            'receiveMethod',
            'adminNotes',
        ]);
    }

    private function recordHistory(
        SellRmbTransfer $transfer,
        ?SellRmbStatus $from,
        SellRmbStatus $to,
        ?string $note,
        ?int $actorId,
    ): void {
        SellRmbStatusHistory::create([
            'sell_rmb_transfer_id' => $transfer->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'actor_id' => $actorId,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timelinePayload(SellRmbTransfer $transfer): array
    {
        $current = $transfer->status;
        $failed = in_array($current, [
            SellRmbStatus::Rejected,
            SellRmbStatus::Cancelled,
            SellRmbStatus::Failed,
        ], true);

        $currentIndex = match ($current) {
            SellRmbStatus::Submitted => 0,
            SellRmbStatus::RmbVerification, SellRmbStatus::RmbReceived => 1,
            SellRmbStatus::PayoutProcessing => 2,
            SellRmbStatus::Paid => 3,
            SellRmbStatus::Completed => 4,
            default => -1,
        };

        return collect(SellRmbStatus::timeline())->values()->map(function (SellRmbStatus $step, int $index) use ($current, $currentIndex, $failed) {
            $done = $currentIndex > $index || $current === SellRmbStatus::Completed;
            $isCurrent = $currentIndex === $index;

            return [
                'key' => $step->value,
                'label' => $step->label(),
                'done' => $done && ! $failed,
                'current' => $isCurrent && ! $failed,
                'failed' => $failed && $isCurrent,
            ];
        })->all();
    }

    private function nextReference(): string
    {
        $date = now()->format('Ymd');
        $count = SellRmbTransfer::query()
            ->where('reference', 'like', "SR-{$date}-%")
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('SR-%s-%05d', $date, $count);
    }

    private function notifyUser(SellRmbTransfer $transfer, SellRmbStatus $status): void
    {
        $transfer->loadMissing('user');
        if (! $transfer->user) {
            return;
        }

        try {
            AppNotificationService::send(
                $transfer->user,
                'sell_rmb',
                $this->userTitle($status),
                $this->userBody($transfer, $status),
                [
                    'transfer_id' => $transfer->id,
                    'reference' => $transfer->reference,
                    'status' => $status->value,
                    'url' => '/wallet/sell-rmb/'.$transfer->id,
                ],
            );
            $transfer->user->notify(new SellRmbUserNotification($transfer, $status));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyAdmins(SellRmbTransfer $transfer, SellRmbStatus $status): void
    {
        try {
            AdminNotifier::notify(new SellRmbAdminNotification($transfer, $status));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function userTitle(SellRmbStatus $status): string
    {
        return match ($status) {
            SellRmbStatus::Submitted => 'Sell RMB submitted',
            SellRmbStatus::RmbVerification => 'RMB verification',
            SellRmbStatus::RmbReceived => 'RMB received',
            SellRmbStatus::PayoutProcessing => 'Sell RMB processing',
            SellRmbStatus::Paid => 'Payout sent',
            SellRmbStatus::Completed => 'Sell RMB completed',
            SellRmbStatus::Rejected => 'Sell RMB rejected',
            SellRmbStatus::Cancelled => 'Sell RMB cancelled',
            SellRmbStatus::Failed => 'Sell RMB failed',
        };
    }

    private function userBody(SellRmbTransfer $transfer, SellRmbStatus $status): string
    {
        return match ($status) {
            SellRmbStatus::Paid, SellRmbStatus::Completed => "{$transfer->reference}: payout of "
                .($transfer->payout_currency === 'ghs' ? 'GH₵' : '$')
                .number_format((float) ($transfer->payout_amount ?? $transfer->expectedPayoutAmount()), 2)
                .' sent.',
            SellRmbStatus::Rejected => $transfer->rejection_reason
                ?: 'Your RMB payment could not be verified.',
            default => "{$transfer->reference} is now {$status->label()}.",
        };
    }
}
