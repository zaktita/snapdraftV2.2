<?php

namespace App\Services;

use App\Models\BetaInvite;
use App\Models\Plan;
use Illuminate\Support\Str;
use RuntimeException;

class BetaInviteIssuer
{
    /**
     * Create a single beta invite code (same defaults as `php artisan beta:invites 1`).
     */
    public function createInvite(
        int $credits = 100,
        int $durationDays = 14,
        int $codeExpiresDays = 60,
        int $maxUses = 1,
    ): BetaInvite {
        $plan = Plan::where('slug', 'beta')->first()
            ?? Plan::where('is_active', true)->orderBy('price')->first();

        if (! $plan) {
            throw new RuntimeException('No plan found for beta invite. Run PlanSeeder.');
        }

        do {
            $code = strtoupper(Str::random(8));
        } while (BetaInvite::where('code', $code)->exists());

        return BetaInvite::create([
            'code' => $code,
            'plan_id' => $plan->id,
            'credits' => $credits,
            'duration_days' => $durationDays,
            'max_uses' => $maxUses,
            'times_used' => 0,
            'expires_at' => now()->addDays($codeExpiresDays),
        ]);
    }
}
