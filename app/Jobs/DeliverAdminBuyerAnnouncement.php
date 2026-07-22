<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Models\AdminBuyerAnnouncement;
use App\Models\User;
use App\Notifications\AdminBuyerAnnouncementNotification;
use App\Services\AppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class DeliverAdminBuyerAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public AdminBuyerAnnouncement $announcement,
    ) {}

    public function handle(): void
    {
        $announcement = $this->announcement->fresh();
        if (! $announcement) {
            return;
        }

        $buyerIds = match ($announcement->audience) {
            'one', 'selected' => collect($announcement->buyer_user_ids ?? [])->filter()->values()->all(),
            'all' => User::query()
                ->where('role', UserRole::Buyer)
                ->pluck('id')
                ->all(),
            default => [],
        };

        if ($buyerIds === []) {
            return;
        }

        $data = [
            'buyer_announcement_id' => $announcement->id,
            'url' => route('notifications.index'),
            'audience' => $announcement->audience,
        ];

        $sent = 0;

        User::query()
            ->where('role', UserRole::Buyer)
            ->whereIn('id', $buyerIds)
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($announcement, $data, &$sent) {
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
                    Notification::send($users, new AdminBuyerAnnouncementNotification(
                        $announcement->title,
                        $announcement->body,
                    ));
                }
            });

        $announcement->update(['recipients_count' => $sent]);
    }
}
