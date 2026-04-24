<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Services\BetaInviteIssuer;
use Illuminate\Console\Command;

class GenerateBetaInvites extends Command
{
    protected $signature = 'beta:invites {count=20} {--credits=100} {--days=14} {--expires=60}';

    protected $description = 'Generate beta invite codes for early-access users';

    public function handle(BetaInviteIssuer $issuer): int
    {
        $count = (int) $this->argument('count');
        $credits = (int) $this->option('credits');
        $days = (int) $this->option('days');
        $expires = (int) $this->option('expires'); // days until the code itself expires

        $plan = Plan::where('slug', 'beta')->first()
            ?? Plan::where('is_active', true)->orderBy('price')->first();

        if (! $plan) {
            $this->error('No active plan found. Run: php artisan db:seed --class=PlanSeeder');

            return 1;
        }

        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $invite = $issuer->createInvite($credits, $days, $expires, 1);

            $rows[] = [
                $i + 1,
                $invite->code,
                $credits.' credits',
                $days.' days access',
                $invite->expires_at?->format('Y-m-d') ?? '—',
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
