<?php

namespace App\Jobs;

use App\Models\AdminAnnouncement;
use App\Models\SellerProfile;
use App\Models\User;
use App\Enums\SellerStatus;
use App\Notifications\AdminSellerAnnouncementNotification;
use App\Services\AppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class DeliverAdminSellerAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public AdminAnnouncement $announcement,
    ) {}

    public function handle(): void
    {
        $announcement = $this->announcement->fresh();
        if (! $announcement) {
            return;
        }

        $sellerIds = match ($announcement->audience) {
            'one', 'selected' => collect($announcement->seller_profile_ids ?? [])->filter()->values()->all(),
            'all' => SellerProfile::query()
                ->where('status', SellerStatus::Approved)
                ->pluck('id')
                ->all(),
            default => [],
        };

        if ($sellerIds === []) {
            return;
        }

        $data = [
            'announcement_id' => $announcement->id,
            'url' => route('notifications.index'),
            'audience' => $announcement->audience,
        ];

        $sent = 0;

        SellerProfile::query()
            ->whereIn('id', $sellerIds)
            ->with('user')
            ->orderBy('id')
            ->chunkById(100, function ($profiles) use ($announcement, $data, &$sent) {
                $users = $profiles->map(fn (SellerProfile $profile) => $profile->user)->filter();

                foreach ($users as $user) {
                    /** @var User $user */
                    AppNotificationService::send(
                        $user,
                        'admin_message',
                        $announcement->title,
                        $announcement->body,
                        $data,
                    );
                    $sent++;
                }

                if ($announcement->send_email) {
                    Notification::send($users, new AdminSellerAnnouncementNotification(
                        $announcement->title,
                        $announcement->body,
                    ));
                }
            });

        $announcement->update(['recipients_count' => $sent]);
    }
}
