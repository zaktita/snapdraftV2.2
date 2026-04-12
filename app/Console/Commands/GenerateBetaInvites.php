<?php

namespace App\Console\Commands;

use App\Models\BetaInvite;
use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateBetaInvites extends Command
{
    protected $signature   = 'beta:invites {count=20} {--credits=100} {--days=14} {--expires=60}';
    protected $description = 'Generate beta invite codes for early-access users';

    public function handle(): int
    {
        $count   = (int) $this->argument('count');
        $credits = (int) $this->option('credits');
        $days    = (int) $this->option('days');
        $expires = (int) $this->option('expires'); // days until the code itself expires

        $plan = Plan::where('slug', 'beta')->first()
            ?? Plan::where('is_active', true)->orderBy('price')->first();

        if (!$plan) {
            $this->error('No active plan found. Run: php artisan db:seed --class=PlanSeeder');
            return 1;
        }

        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            // Generate a unique 8-char uppercase code
            do {
                $code = strtoupper(Str::random(8));
            } while (BetaInvite::where('code', $code)->exists());

            BetaInvite::create([
                'code'         => $code,
                'plan_id'      => $plan->id,
                'credits'      => $credits,
                'duration_days' => $days,
                'max_uses'     => 1,
                'times_used'   => 0,
                'expires_at'   => now()->addDays($expires),
            ]);

            $rows[] = [
                $i + 1,
                $code,
                $credits . ' credits',
                $days . ' days access',
                now()->addDays($expires)->format('Y-m-d'),
            ];
        }

        $this->table(
            ['#', 'Code', 'Credits', 'Access Duration', 'Code Expires'],
            $rows
        );

        $this->info("✓ Generated {$count} beta invite codes (plan: {$plan->name}).");
        return 0;
    }
}
