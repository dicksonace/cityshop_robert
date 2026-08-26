<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\StatusView;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StatusService
{
    public const LIFETIME_HOURS = 24;

    public const MAX_ACTIVE = 20;

    /**
     * @param  array{type?: string, body?: ?string, background_color?: ?string}  $payload
     */
    public static function post(
        User $user,
        array $payload,
        ?UploadedFile $image = null,
        ?UploadedFile $video = null,
    ): UserStatus {
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        static::pruneExpiredFor($user);

        $active = UserStatus::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->count();
        abort_if($active >= static::MAX_ACTIVE, 422, 'You can have at most 20 statuses at a time.');
        abort_if($image && $video, 422, 'Send either an image or a video, not both.');

        $type = $video ? 'video' : ($image ? 'image' : 'text');
        $body = trim((string) ($payload['body'] ?? ''));
        $color = static::sanitizeColor($payload['background_color'] ?? null);

        if ($type === 'text') {
            abort_if($body === '', 422, 'Write something for your status.');
        }

        $path = null;
        if ($video) {
            $path = ProductVideoService::storeUploaded($video, 'status/'.$user->id);
        } elseif ($image) {
            $path = $image->store('status/'.$user->id, 'public');
        }

        return UserStatus::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'body' => $body !== '' ? $body : null,
            'media_path' => $path,
            'background_color' => $type === 'text' ? ($color ?? '#EA580C') : $color,
            'expires_at' => now()->addHours(static::LIFETIME_HOURS),
        ]);
    }

    /**
     * @return array{mine: array<string, mixed>, users: list<array<string, mixed>>}
     */
    public static function feed(User $viewer): array
    {
        abort_unless(in_array($viewer->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        static::pruneExpired();

        $blockedIds = UserBlockService::blockedUserIds($viewer);

        $statuses = UserStatus::query()
            ->with([
                'user:id,name,avatar,role,deleted_at',
                'user.sellerProfile:id,user_id,shop_photo,store_name,business_name',
            ])
            ->where('expires_at', '>', now())
            ->whereHas('user', function ($query) {
                $query->whereIn('role', [UserRole::Buyer, UserRole::Seller])
                    ->whereNull('deleted_at');
            })
            ->when($blockedIds !== [], fn ($query) => $query->whereNotIn('user_id', $blockedIds))
            ->orderBy('created_at')
            ->get();

        $viewedIds = StatusView::query()
            ->where('viewer_id', $viewer->id)
            ->whereIn('user_status_id', $statuses->pluck('id'))
            ->pluck('user_status_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ownViewCounts = [];
        $mineItems = $statuses->where('user_id', $viewer->id)->values();
        if ($mineItems->isNotEmpty()) {
            $ownViewCounts = StatusView::query()
                ->whereIn('user_status_id', $mineItems->pluck('id'))
                ->selectRaw('user_status_id, count(*) as views_count')
                ->groupBy('user_status_id')
                ->pluck('views_count', 'user_status_id')
                ->all();
        }

        $grouped = [];
        foreach ($statuses as $status) {
            $ownerId = (int) $status->user_id;
            $grouped[$ownerId] ??= [];
            $grouped[$ownerId][] = $status;
        }

        $mine = [
            'user' => static::formatUser($viewer),
            'items' => $mineItems->map(
                fn (UserStatus $status) => static::formatStatus(
                    $status,
                    $viewer,
                    viewedIds: $viewedIds,
                    viewCount: (int) ($ownViewCounts[$status->id] ?? 0),
                )
            )->values()->all(),
            'unseen_count' => 0,
        ];

        $users = [];
        foreach ($grouped as $ownerId => $items) {
            if ((int) $ownerId === (int) $viewer->id) {
                continue;
            }
            $owner = $items[0]->user;
            if (! $owner) {
                continue;
            }
            $formatted = array_map(
                fn (UserStatus $status) => static::formatStatus($status, $viewer, viewedIds: $viewedIds),
                $items,
            );
            $unseen = count(array_filter($formatted, fn (array $row) => ! $row['viewed']));
            $users[] = [
                'user' => static::formatUser($owner),
                'items' => $formatted,
                'unseen_count' => $unseen,
                'latest_at' => end($items)?->created_at?->toIso8601String(),
            ];
        }

        usort($users, function (array $a, array $b) {
            $unseen = ($b['unseen_count'] <=> $a['unseen_count']);
            if ($unseen !== 0) {
                return $unseen;
            }

            return strcmp((string) $b['latest_at'], (string) $a['latest_at']);
        });

        return [
            'mine' => $mine,
            'users' => $users,
        ];
    }

    public static function markViewed(UserStatus $status, User $viewer): array
    {
        abort_if($status->isExpired(), 404, 'This status has expired.');
        abort_unless(in_array($viewer->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $status->loadMissing('user');
        $owner = $status->user;
        abort_unless($owner, 404);

        if ((int) $owner->id !== (int) $viewer->id) {
            abort_if(UserBlockService::isBlockedEitherWay($viewer, $owner), 403);
            StatusView::query()->firstOrCreate(
                [
                    'user_status_id' => $status->id,
                    'viewer_id' => $viewer->id,
                ],
                ['viewed_at' => now()],
            );
        }

        return static::formatStatus($status, $viewer, viewedIds: [(int) $status->id]);
    }

    /**
     * @return array{view_count: int, viewers: list<array{id: int, name: string, avatar: ?string, viewed_at: ?string}>}
     */
    public static function views(UserStatus $status, User $user): array
    {
        abort_if($status->isExpired(), 404, 'This status has expired.');
        abort_unless((int) $status->user_id === (int) $user->id, 403);

        $views = StatusView::query()
            ->where('user_status_id', $status->id)
            ->with(['viewer:id,name,avatar,role,deleted_at'])
            ->orderByDesc('viewed_at')
            ->get();

        return [
            'view_count' => $views->count(),
            'viewers' => $views->map(function (StatusView $view) {
                $viewer = $view->viewer;
                if (! $viewer || $viewer->trashed()) {
                    return [
                        'id' => (int) $view->viewer_id,
                        'name' => 'Deleted account',
                        'avatar' => null,
                        'viewed_at' => $view->viewed_at?->toIso8601String(),
                    ];
                }

                return [
                    'id' => $viewer->id,
                    'name' => $viewer->name ?: 'User',
                    'avatar' => $viewer->publicAvatarUrl(),
                    'viewed_at' => $view->viewed_at?->toIso8601String(),
                ];
            })->values()->all(),
        ];
    }

    public static function destroy(UserStatus $status, User $user): void
    {
        abort_unless((int) $status->user_id === (int) $user->id, 403);

        if (is_string($status->media_path) && $status->media_path !== '') {
            Storage::disk('public')->delete($status->media_path);
        }

        $status->delete();
    }

    public static function pruneExpired(): void
    {
        $expired = UserStatus::query()
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $status) {
            if (is_string($status->media_path) && $status->media_path !== '') {
                Storage::disk('public')->delete($status->media_path);
            }
            $status->delete();
        }
    }

    public static function pruneExpiredFor(User $user): void
    {
        $expired = UserStatus::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $status) {
            if (is_string($status->media_path) && $status->media_path !== '') {
                Storage::disk('public')->delete($status->media_path);
            }
            $status->delete();
        }
    }

    /**
     * @param  list<int>  $viewedIds
     * @return array<string, mixed>
     */
    public static function formatStatus(
        UserStatus $status,
        User $viewer,
        array $viewedIds = [],
        ?int $viewCount = null,
    ): array {
        $isOwner = (int) $status->user_id === (int) $viewer->id;

        return [
            'id' => $status->id,
            'type' => $status->type,
            'body' => $status->body,
            'media_url' => $status->mediaUrl(),
            'background_color' => $status->background_color,
            'created_at' => $status->created_at?->toIso8601String(),
            'expires_at' => $status->expires_at?->toIso8601String(),
            'viewed' => $isOwner || in_array((int) $status->id, $viewedIds, true),
            'view_count' => $isOwner ? ($viewCount ?? $status->views()->count()) : null,
        ];
    }

    /**
     * @return array{id: int, name: string, avatar: ?string, role: ?string}
     */
    public static function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name ?: 'User',
            'avatar' => $user->publicAvatarUrl(),
            'role' => $user->role?->value,
        ];
    }

    private static function sanitizeColor(?string $color): ?string
    {
        if (! is_string($color) || $color === '') {
            return null;
        }

        $value = strtoupper(trim($color));
        if (preg_match('/^#[0-9A-F]{6}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
