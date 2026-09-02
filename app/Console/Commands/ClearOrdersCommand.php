<?php

namespace App\Console\Commands;

use App\Models\Checkout;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearOrdersCommand extends Command
{
    protected $signature = 'cityshop:clear-orders
                            {--force : Required. Permanently delete all orders and related checkouts/payments.}
                            {--keep-wallets : Leave seller pending balances and order wallet ledger rows as-is.}';

    protected $description = 'Delete ALL marketplace orders (and checkouts/payments). Clears admin order queues and paid revenue.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to run without --force.');
            $this->line('Example: php artisan cityshop:clear-orders --force');

            return self::FAILURE;
        }

        $orderCount = Order::query()->count();
        $checkoutCount = Checkout::query()->count();

        $this->warn("About to permanently delete {$orderCount} order(s) and {$checkoutCount} checkout(s).");

        DB::transaction(function (): void {
            if (! $this->option('keep-wallets') && Schema::hasTable('wallet_transactions')) {
                $deletedTx = WalletTransaction::query()
                    ->where(function ($q): void {
                        $q->whereNotNull('order_item_id')
                            ->orWhere('reference', 'like', 'SHIP-%')
                            ->orWhereIn('type', [
                                'sale_pending',
                                'sale_released',
                                'sale_reversed',
                                'order_payment',
                                'order_refund',
                                'direct_cancel_debit',
                            ]);
                    })
                    ->delete();

                $this->line("Deleted {$deletedTx} order-related wallet transaction(s).");

                // Pending funds are held against open orders — clear them so sellers don't keep ghost pending.
                $walletsUpdated = Wallet::query()
                    ->where('pending_balance', '>', 0)
                    ->update(['pending_balance' => 0]);

                $this->line("Zeroed pending_balance on {$walletsUpdated} wallet(s).");
            }

            // Orders cascade to order_items, reviews, disputes; nulls payment/invoice order_id.
            $deletedOrders = Order::query()->delete();
            $this->line("Deleted {$deletedOrders} order(s).");

            // Checkouts cascade to payments and invoices.
            $deletedCheckouts = Checkout::query()->delete();
            $this->line("Deleted {$deletedCheckouts} checkout(s).");

            if (Schema::hasTable('seller_profiles') && Schema::hasColumn('seller_profiles', 'total_sales')) {
                DB::table('seller_profiles')->update(['total_sales' => 0]);
                $this->line('Reset seller_profiles.total_sales to 0.');
            }

            if (Schema::hasTable('products') && Schema::hasColumn('products', 'purchase_count')) {
                DB::table('products')->update(['purchase_count' => 0]);
                $this->line('Reset products.purchase_count to 0.');
            }
        });

        $this->newLine();
        $this->info('All orders cleared.');
        $this->comment('Admin queues (unprocessed / awaiting confirm / pending funds / paid revenue) should now be empty.');

        return self::SUCCESS;
    }
}
