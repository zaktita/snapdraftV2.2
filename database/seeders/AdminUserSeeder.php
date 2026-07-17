<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Local/dev only. Never run this seeder on production with default credentials.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('AdminUserSeeder skipped in production - create admins manually with a strong password.');

            return;
        }

        $password = env('ADMIN_SEED_PASSWORD');
        if (! is_string($password) || strlen($password) < 12) {
            $this->command?->error('Set ADMIN_SEED_PASSWORD (min 12 chars) in .env before seeding an admin user.');

            return;
        }

        User::updateOrCreate(
            ['email' => env('ADMIN_SEED_EMAIL', 'admin@snapdraft.com')],
            [
                'name' => 'Admin User',
                'password' => Hash::make($password),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Admin user ensured for local/dev (password from ADMIN_SEED_PASSWORD).');
    }
}
