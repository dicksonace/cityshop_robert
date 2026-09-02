<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SetAdminCredentialsCommand extends Command
{
    protected $signature = 'cityshop:set-admin
                            {--email= : New admin email (login)}
                            {--password= : New admin password}
                            {--name= : Optional display name}
                            {--force : Skip confirmation}';

    protected $description = 'Create or update the Super Admin web login credentials';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: $this->ask('Admin email', 'admin@cityshop.com'));
        $password = (string) ($this->option('password') ?: $this->secret('Admin password'));
        $name = (string) ($this->option('name') ?: 'Super Admin');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Set admin login to {$email}?", true)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $admin = User::query()
            ->where('role', UserRole::Admin)
            ->orderBy('id')
            ->first();

        if ($admin) {
            $admin->fill([
                'name' => $name !== '' ? $name : $admin->name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
            ]);
            $admin->save();
            $this->info("Updated existing admin #{$admin->id}.");
        } else {
            $admin = User::query()->create([
                'name' => $name !== '' ? $name : 'Super Admin',
                'email' => $email,
                'mobile' => '0200000000',
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
            ]);
            $this->info("Created admin #{$admin->id}.");
        }

        $this->newLine();
        $this->line('Web admin login URL: /admin24/login');
        $this->line("Email: {$email}");
        $this->comment('Password updated (not shown).');

        return self::SUCCESS;
    }
}
