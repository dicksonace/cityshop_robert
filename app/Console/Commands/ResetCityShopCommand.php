<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetCityShopCommand extends Command
{
    protected $signature = 'cityshop:reset
                            {--force : Required on production to wipe all data}';

    protected $description = 'Wipe the database and seed a fresh install (admin + categories only, no demo products)';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Production reset blocked. Run with --force if you intend to delete ALL data.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This will DELETE all users, products, and orders. Continue?', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $this->warn('Resetting database…');

        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

        $this->newLine();
        $this->info('CityShop reset complete.');
        $this->line('  Admin login: /admin/login');
        $this->line('  Email:       admin@cityshop.com');
        $this->line('  Password:    password');
        $this->newLine();
        $this->comment('Change the admin password after first login.');

        return self::SUCCESS;
    }
}
