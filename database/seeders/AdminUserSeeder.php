<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user if not exists
        User::firstOrCreate(
            ['email' => 'admin@snapdraft.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'subscription_tier' => 'scale',
                'credits_remaining' => 999999,
                'credits_total' => 999999,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Admin user created: admin@snapdraft.com / password');
    }
}
