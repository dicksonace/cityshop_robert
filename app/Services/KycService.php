<?php

namespace App\Services;

use App\Enums\KycStatus;
use App\Models\KycVerification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class KycService
{
    public static function latest(?User $user): ?KycVerification
    {
        if (! $user || ! $user->id) {
            return null;
        }

        return $user->latestKyc()->first();
    }

    public static function canStoreFunds(?User $user): bool
    {
        return static::latest($user)?->status === KycStatus::Approved;
    }

    public static function status(?User $user): string
    {
        return static::latest($user)?->status?->value ?? 'unverified';
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(?User $user, bool $withPhotos = true): array
    {
        $kyc = static::latest($user);
        $status = $kyc?->status?->value ?? 'unverified';
        $label = $kyc?->status?->label() ?? 'Not verified';
        $notesVisible = in_array($status, ['rejected', 'needs_improvement'], true);

        $payload = [
            'id' => $kyc?->id,
            'status' => $status,
            'status_label' => $label,
            'can_store_funds' => $status === 'approved',
            'can_submit' => $status !== 'approved',
            'ghana_card_number' => $kyc?->ghana_card_number,
            'full_name' => $kyc?->full_name,
            'admin_notes' => $notesVisible ? $kyc?->admin_notes : null,
            'submitted_at' => $kyc?->submitted_at?->toIso8601String(),
            'reviewed_at' => $kyc?->reviewed_at?->toIso8601String(),
        ];

        if ($withPhotos && $kyc) {
            $payload['front_url'] = $kyc->publicUrl($kyc->front_path);
            $payload['back_url'] = $kyc->publicUrl($kyc->back_path);
            $payload['selfie_url'] = $kyc->publicUrl($kyc->selfie_path);
        }

        return $payload;
    }

    public static function denyStoreFundsMessage(?User $user): string
    {
        return match (static::status($user)) {
            'pending' => 'Your Ghana Card is waiting for system approval. You can still buy items with Paystack.',
            'needs_improvement' => 'Please improve your Ghana Card photos before you can transact with the CityShop wallet.',
            'rejected' => 'Your Ghana Card was not approved. Update it and submit again to transact with the CityShop wallet.',
            default => 'The system must approve your Ghana Card before you can transact with the CityShop wallet.',
        };
    }

    public static function denyStoreFundsResponse(?User $user): ?JsonResponse
    {
        if (static::canStoreFunds($user)) {
            return null;
        }

        return response()->json([
            'message' => static::denyStoreFundsMessage($user),
            'code' => 'kyc_required',
            'kyc' => static::payload($user),
        ], 403);
    }

    public static function denyStoreFundsRedirect(?User $user): ?RedirectResponse
    {
        if (static::canStoreFunds($user)) {
            return null;
        }

        return back()->with('error', static::denyStoreFundsMessage($user));
    }

    public static function normalizeGhanaCard(string $raw): string
    {
        $compact = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $raw));
        if (preg_match('/^GHA(\d{8,12})(\d)$/', $compact, $m)) {
            return 'GHA-'.$m[1].'-'.$m[2];
        }

        return $compact;
    }

    public static function assertValidGhanaCard(string $normalized): void
    {
        if (! preg_match('/^GHA-\d{8,12}-\d$/', $normalized)) {
            throw ValidationException::withMessages([
                'ghana_card_number' => 'Enter a valid Ghana Card number, for example GHA-123456789-1.',
            ]);
        }
    }

    /**
     * @param  array{ghana_card_number: string, full_name?: ?string, front: UploadedFile, back: UploadedFile, selfie?: ?UploadedFile}  $data
     */
    public static function submit(User $user, array $data): KycVerification
    {
        $number = static::normalizeGhanaCard($data['ghana_card_number']);
        static::assertValidGhanaCard($number);

        $taken = KycVerification::query()
            ->where('ghana_card_number', $number)
            ->where('status', KycStatus::Approved)
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'ghana_card_number' => 'This Ghana Card is already verified on another CityShop account.',
            ]);
        }

        $existing = static::latest($user);
        if ($existing?->status === KycStatus::Approved) {
            throw ValidationException::withMessages([
                'ghana_card_number' => 'Your Ghana Card is already verified.',
            ]);
        }

        $frontPath = $data['front']->store('kyc/front', 'public');
        $backPath = $data['back']->store('kyc/back', 'public');
        $selfiePath = isset($data['selfie']) && $data['selfie'] instanceof UploadedFile
            ? $data['selfie']->store('kyc/selfie', 'public')
            : null;

        if ($existing) {
            Storage::disk('public')->delete(array_filter([
                $existing->front_path,
                $existing->back_path,
                $existing->selfie_path,
            ]));
            $existing->update([
                'ghana_card_number' => $number,
                'full_name' => $data['full_name'] ?? $existing->full_name,
                'front_path' => $frontPath,
                'back_path' => $backPath,
                'selfie_path' => $selfiePath ?? $existing->selfie_path,
                'status' => KycStatus::Pending,
                'admin_notes' => null,
                'reviewed_by' => null,
                'submitted_at' => now(),
                'reviewed_at' => null,
            ]);
            $kyc = $existing->fresh();
        } else {
            $kyc = KycVerification::create([
                'user_id' => $user->id,
                'ghana_card_number' => $number,
                'full_name' => $data['full_name'] ?? null,
                'front_path' => $frontPath,
                'back_path' => $backPath,
                'selfie_path' => $selfiePath,
                'status' => KycStatus::Pending,
                'submitted_at' => now(),
            ]);
        }

        $user->forceFill(['ghana_card_number' => $number])->save();

        return $kyc;
    }

    public static function approve(KycVerification $kyc, User $admin): KycVerification
    {
        $kyc->update([
            'status' => KycStatus::Approved,
            'admin_notes' => null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        $kyc->user?->forceFill(['ghana_card_number' => $kyc->ghana_card_number])->save();

        return $kyc->fresh('user');
    }

    public static function reject(KycVerification $kyc, User $admin, string $notes): KycVerification
    {
        $kyc->update([
            'status' => KycStatus::Rejected,
            'admin_notes' => $notes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $kyc->fresh('user');
    }

    public static function requestChanges(KycVerification $kyc, User $admin, string $notes): KycVerification
    {
        $kyc->update([
            'status' => KycStatus::NeedsImprovement,
            'admin_notes' => $notes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $kyc->fresh('user');
    }
}
