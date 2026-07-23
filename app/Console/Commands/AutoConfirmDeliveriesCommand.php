<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Support\DeliveryConfirmationPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoConfirmDeliveriesCommand extends Command
{
    protected $signature = 'orders:auto-confirm-deliveries';

    protected $description = 'Auto-confirm deliveries after the buyer confirmation window expires';

    public function handle(OrderService $orders): int
    {
        $days = DeliveryConfirmationPolicy::days();
        $cutoff = now()->subDays($days);

        $items = OrderItem::query()
            ->where('status', OrderStatus::AwaitingConfirmation)
            ->where(function ($q) use ($cutoff) {
                $q->where(function ($inner) use ($cutoff) {
                    $inner->whereNotNull('awaiting_confirmation_at')
                        ->where('awaiting_confirmation_at', '<=', $cutoff);
                })->orWhere(function ($inner) use ($cutoff) {
                    $inner->whereNull('awaiting_confirmation_at')
                        ->where('updated_at', '<=', $cutoff);
                });
            })
            ->with(['order', 'seller'])
            ->get();

        $confirmed = 0;
        $failed = 0;

        foreach ($items as $item) {
            try {
                $orders->confirmBuyerDelivery($item);
                $confirmed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Auto-confirm delivery failed', [
                    'order_item_id' => $item->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Auto-confirmed {$confirmed} delivery(ies); {$failed} failed (window {$days} days).");

        return self::SUCCESS;
    }
}
