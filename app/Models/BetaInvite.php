<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BetaInvite extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'plan_id',
        'credits',
        'duration_days',
        'max_uses',
        'times_used',
        'expires_at',
    ];

    protected $casts = [
        'credits'      => 'integer',
        'duration_days' => 'integer',
        'max_uses'     => 'integer',
        'times_used'   => 'integer',
        'expires_at'   => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'beta_invite_redemptions')
            ->withPivot('redeemed_at')
            ->withTimestamps();
    }

    /**
     * Check whether this invite can still be redeemed.
     */
    public function isValid(): bool
    {
        if ($this->times_used >= $this->max_uses) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Create an active Subscription for $user from this invite and record the redemption.
     * Duration defaults to 30 days (1 month) unless the invite overrides duration_days.
     */
    public function redeem(User $user): void
    {
        DB::transaction(function () use ($user) {
            /** @var self $invite */
            $invite = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            if (! $invite->isValid()) {
                throw new RuntimeException('This invite code is no longer valid.');
            }

            if ($user->subscriptions()->where('provider', 'invite_code')->where('status', 'active')->exists()) {
                throw new RuntimeException('You already have an active beta invitation.');
            }

            $plan = $invite->plan;
            $planCapabilities = $plan->capabilities ?? [];
            $durationDays = max(1, (int) ($invite->duration_days ?: 30));

            $user->subscriptions()->create([
                'plan_id'        => $plan->id,
                'status'         => 'active',
                'provider'       => 'invite_code',
                'billing_period' => 'beta',
                'starts_at'      => now(),
                'ends_at'        => now()->addDays($durationDays),
                'price'          => 0,
                'amount_paid'    => 0,
                'currency'       => 'USD',
                'capabilities'   => array_merge($planCapabilities, [
                    'credits_remaining' => $invite->credits,
                    'credits_limit'     => $invite->credits,
                    'credits_used'      => 0,
                    'credits_per_month' => $invite->credits,
                ]),
            ]);

            $invite->increment('times_used');

            DB::table('beta_invite_redemptions')->insert([
                'id'             => Str::orderedUuid()->toString(),
                'beta_invite_id' => $invite->id,
                'user_id'        => $user->id,
                'redeemed_at'    => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        });
    }
}
