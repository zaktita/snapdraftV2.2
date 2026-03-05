<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'plan_id',
        // Lemon Squeezy fields
        'lemonsqueezy_id',
        'lemonsqueezy_customer_id',
        'lemonsqueezy_order_id',
        'lemonsqueezy_variant_id',
        'lemonsqueezy_product_id',
        'name',
        // Subscription details
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        // Billing
        'billing_period', // Renamed from billing_cycle
        'amount_paid',
        'price',
        'currency',
        // Payment provider
        'provider',
        'provider_subscription_id',
        'provider_customer_id',
        // Cancellation
        'cancelled_at',
        'cancellation_reason',
        // Renewal
        'auto_renew',
        'next_billing_at',
        'renews_at',
        // Lemon Squeezy specific
        'paused_at',
        'card_brand',
        'card_last_four',
        'update_payment_url',
        'customer_portal_url',
        // Capabilities tracking
        'capabilities',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'amount_paid' => 'decimal:2',
        'price' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
        'next_billing_at' => 'datetime',
        'renews_at' => 'datetime',
        'paused_at' => 'datetime',
        'capabilities' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plan for this subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the usage record for this subscription.
     */
    public function usage(): HasOne
    {
        return $this->hasOne(SubscriptionUsage::class);
    }

    /**
     * Get the transactions for this subscription.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if subscription is past due.
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Check if subscription has expired.
     */
    public function hasExpired(): bool
    {
        return $this->status === 'expired';
    }

    /**
     * Check if subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if subscription has ended.
     */
    public function hasEnded(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    /**
     * Scope active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope cancelled subscriptions.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope expired subscriptions.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Get credits remaining.
     */
    public function creditsRemaining(): int
    {
        return (int) ($this->capabilities['credits_remaining'] ?? 0);
    }

    /**
     * Get credits used.
     */
    public function creditsUsed(): int
    {
        return (int) ($this->capabilities['credits_used'] ?? 0);
    }

    /**
     * Get credits limit.
     */
    public function creditsLimit(): int
    {
        return (int) ($this->capabilities['credits_limit'] ?? 0);
    }

    /**
     * Check if subscription has credits.
     */
    public function hasCredits(int $amount = 1): bool
    {
        return $this->creditsRemaining() >= $amount;
    }

    /**
     * Use credits from subscription.
     */
    public function useCredits(int $amount = 1): void
    {
        if (!$this->hasCredits($amount)) {
            throw new \RuntimeException('Insufficient credits');
        }

        $capabilities = $this->capabilities ?? [];
        $capabilities['credits_used'] = ($capabilities['credits_used'] ?? 0) + $amount;
        $capabilities['credits_remaining'] = ($capabilities['credits_remaining'] ?? 0) - $amount;
        
        $this->update(['capabilities' => $capabilities]);
        
        // Also update user's total_generations
        $this->user->increment('total_generations');
        $this->user->update(['last_generation_at' => now()]);
    }

    /**
     * Refund credits to subscription.
     */
    public function refundCredits(int $amount = 1): void
    {
        $capabilities = $this->capabilities ?? [];
        $capabilities['credits_used'] = max(0, ($capabilities['credits_used'] ?? 0) - $amount);
        $capabilities['credits_remaining'] = ($capabilities['credits_remaining'] ?? 0) + $amount;
        
        $this->update(['capabilities' => $capabilities]);
        
        // Also update user's total_generations
        $this->user->decrement('total_generations');
    }

    /**
     * Reset monthly credits based on plan.
     */
    public function resetMonthlyCredits(): void
    {
        $plan = $this->plan;
        if (!$plan) {
            return;
        }

        $planCapabilities = $plan->capabilities ?? [];
        $creditsLimit = $planCapabilities['credits_per_month'] ?? 0;

        $capabilities = $this->capabilities ?? [];
        $capabilities['credits_used'] = 0;
        $capabilities['credits_remaining'] = $creditsLimit;
        $capabilities['credits_limit'] = $creditsLimit;
        
        $this->update(['capabilities' => $capabilities]);
    }

    /**
     * Get projects count (reads from actual DB count via user relationship).
     */
    public function projectsUsed(): int
    {
        // Prefer live DB count over stale capabilities counter
        if ($this->relationLoaded('user') || $this->user_id) {
            try {
                return (int) $this->user()->withCount('projects')->first()?->projects_count ?? 0;
            } catch (\Throwable) {
                // fall through to capabilities fallback
            }
        }
        return (int) ($this->capabilities['projects_used'] ?? 0);
    }

    /**
     * Get projects limit.
     * Supports both 'projects_limit' and legacy 'max_projects' capability keys.
     */
    public function projectsLimit(): int
    {
        $caps = $this->capabilities ?? [];
        // Check both key variants for backwards compatibility
        if (isset($caps['projects_limit'])) {
            return (int) $caps['projects_limit'];
        }
        if (isset($caps['max_projects'])) {
            return (int) $caps['max_projects'];
        }
        return 0;
    }

    /**
     * Check if can create more projects.
     * A limit of 0 means unlimited (no cap enforced).
     */
    public function canCreateProject(): bool
    {
        $limit = $this->projectsLimit();
        // 0 = no limit set, treat as unlimited
        if ($limit === 0) {
            return true;
        }
        return $this->projectsUsed() < $limit;
    }

    /**
     * Check if has specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->capabilities['features'] ?? [];
        return $features[$feature] ?? false;
    }
}
