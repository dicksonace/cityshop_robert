<?php

namespace App\Services;

use App\Enums\LivestreamStatus;
use App\Enums\SellerStatus;
use App\Models\Livestream;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LiveStreamService
{
    public static function expireStale(): void
    {
        Livestream::query()
            ->where('status', LivestreamStatus::Live)
            ->where('last_heartbeat_at', '<', now()->subMinutes(5))
            ->update([
                'status' => LivestreamStatus::Ended->value,
                'ended_at' => now(),
            ]);
    }

    public static function currentForSeller(User $seller): ?Livestream
    {
        static::expireStale();

        return Livestream::query()
            ->where('seller_id', $seller->id)
            ->where('status', LivestreamStatus::Live)
            ->latest('started_at')
            ->first();
    }

    public static function currentForStore(SellerProfile $store): ?Livestream
    {
        static::expireStale();

        return Livestream::query()
            ->where('seller_id', $store->user_id)
            ->where('status', LivestreamStatus::Live)
            ->latest('started_at')
            ->first();
    }

    public static function start(User $seller, ?string $title = null): Livestream
    {
        $profile = $seller->sellerProfile;
        abort_unless($profile && $profile->status === SellerStatus::Approved, 403, 'Only approved sellers can go live.');

        $existing = static::currentForSeller($seller);
        if ($existing) {
            $existing->forceFill(['last_heartbeat_at' => now()])->save();

            return $existing;
        }

        return Livestream::create([
            'seller_id' => $seller->id,
            'title' => filled($title) ? Str::limit(trim($title), 80, '') : ($profile->displayName().' live'),
            'room_name' => 'CityShopLive'.Str::lower(Str::random(18)),
            'provider' => 'jitsi',
            'status' => LivestreamStatus::Live,
            'started_at' => now(),
            'last_heartbeat_at' => now(),
        ]);
    }

    public static function heartbeat(User $seller): ?Livestream
    {
        $live = static::currentForSeller($seller);
        if (! $live) {
            return null;
        }

        $live->forceFill(['last_heartbeat_at' => now()])->save();

        return $live;
    }

    public static function end(User $seller): ?Livestream
    {
        $live = Livestream::query()
            ->where('seller_id', $seller->id)
            ->where('status', LivestreamStatus::Live)
            ->latest('started_at')
            ->first();

        if (! $live) {
            return null;
        }

        $live->forceFill([
            'status' => LivestreamStatus::Ended,
            'ended_at' => now(),
        ])->save();

        return $live;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function liveNow(int $limit = 12): Collection
    {
        static::expireStale();

        return Livestream::query()
            ->with(['seller.sellerProfile'])
            ->where('status', LivestreamStatus::Live)
            ->latest('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (Livestream $live) => static::card($live, withRoom: false))
            ->filter()
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function card(Livestream $live, bool $withRoom = false): ?array
    {
        $profile = $live->seller?->sellerProfile;
        if (! $profile || $profile->status !== SellerStatus::Approved) {
            return null;
        }

        $photoUrl = $live->seller?->publicAvatarUrl();
        if (! $photoUrl && filled($profile->shop_photo)) {
            $photo = $profile->shop_photo;
            $photoUrl = str_starts_with((string) $photo, 'http')
                ? $photo
                : Storage::disk('public')->url($photo);
        }
        if (is_string($photoUrl) && str_starts_with($photoUrl, '/')) {
            $photoUrl = url($photoUrl);
        }

        $payload = [
            'id' => $live->id,
            'seller_id' => $live->seller_id,
            'title' => $live->title,
            'store_name' => $profile->displayName(),
            'store_slug' => $profile->slug,
            'shop_photo' => $photoUrl,
            'started_at' => $live->started_at?->toIso8601String(),
        ];

        if ($withRoom) {
            $payload['room'] = static::roomPayload($live);
        }

        return $payload;
    }

    /**
     * @return array{provider: string, domain: string, room_name: string}
     */
    public static function roomPayload(Livestream $live): array
    {
        return [
            'provider' => $live->provider ?: 'jitsi',
            'domain' => (string) config('services.livestream.jitsi_domain', 'meet.jit.si'),
            'room_name' => $live->room_name,
        ];
    }
}
