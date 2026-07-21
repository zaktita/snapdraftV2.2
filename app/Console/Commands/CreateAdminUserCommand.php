<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdminUserCommand extends Command
{
    protected $signature = 'admin:create
        {email : Admin email address}
        {--name=Admin : Display name}
        {--password= : Password (min 12 chars). Generated if omitted}';

    protected $description = 'Create or promote a production admin (AdminUserSeeder is skipped in production)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name'));
        $password = $this->option('password');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name],
            [
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'max:255'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $generated = false;
        if (! is_string($password) || strlen($password) < 12) {
            $password = Str::password(20);
            $generated = true;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->fill([
                'name' => $name,
                'password' => Hash::make($password),
            ]);
            $user->forceFill([
                'is_admin' => true,
                'is_suspended' => false,
                'suspension_reason' => null,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $this->info("Promoted existing user to admin: {$email}");
        } else {
            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            $user->forceFill([
                'is_admin' => true,
                'email_verified_at' => now(),
            ])->save();

            $this->info("Created admin user: {$email}");
        }

        if ($generated) {
            $this->warn("Generated password (save now): {$password}");
        }

        $this->comment('Enable 2FA in the app before using /admin outside local.');

        return self::SUCCESS;
    }
}
