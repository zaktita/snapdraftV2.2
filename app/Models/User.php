<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'last_generation_at',
        'total_generations',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_suspended' => 'boolean',
            'last_generation_at' => 'datetime',
        ];
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    /**
     * Check if user has credits remaining.
     */
    public function hasCredits(int $amount = 1): bool
    {
        $subscription = $this->subscription();
        return $subscription && $subscription->hasCredits($amount);
    }

    /**
     * Decrement user credits via subscription.
     * 
     * @param int $amount Number of credits to deduct (default 1, or 4 for text-accurate)
     */
    public function useCredit(int $amount = 1): void
    {
        $subscription = $this->subscription();
        if (!$subscription) {
            throw new \RuntimeException('No active subscription found');
        }

        $subscription->useCredits($amount);
    }

    /**
     * Refund user credits via subscription.
     * 
     * @param int $amount Number of credits to refund
     */
    public function refundCredit(int $amount = 1): void
    {
        $subscription = $this->subscription();
        if (!$subscription) {
            throw new \RuntimeException('No active subscription found');
        }

        $subscription->refundCredits($amount);
    }

    /**
     * Reset monthly credits based on subscription.
     * This is called by scheduled jobs for billing cycle renewal.
     */
    public function resetMonthlyCredits(): void
    {
        $subscription = $this->subscription();
        if ($subscription) {
            $subscription->resetMonthlyCredits();
        }
    }

    /**
     * Get user's credits remaining from active subscription.
     */
    public function creditsRemaining(): int
    {
        $subscription = $this->subscription();
        return $subscription ? $subscription->creditsRemaining() : 0;
    }

    /**
     * Get user's total credits limit from active subscription.
     */
    public function creditsTotal(): int
    {
        $subscription = $this->subscription();
        return $subscription ? $subscription->creditsLimit() : 0;
    }

    /**
     * Get the projects for the user.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the generation history for the user.
     */
    public function generationHistory(): HasMany
    {
        return $this->hasMany(GenerationHistory::class);
    }

    /**
     * Get the user's subscriptions.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the user's transactions.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the user's invoices.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the user's subscription usage records.
     */
    public function subscriptionUsages(): HasMany
    {
        return $this->hasMany(SubscriptionUsage::class);
    }

    /**
     * Get the user's active subscription.
     */
    public function subscription()
    {
        return $this->subscriptions()->where('status', 'active')->latest()->first();
    }

    /**
     * Check if user has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription() !== null;
    }

    /**
     * Get the current subscription tier from active subscription.
     * Returns null if no active subscription.
     */
    public function currentTier(): ?string
    {
        return $this->subscription()?->name;
    }

    /**
     * Get the current plan from active subscription.
     */
    public function currentPlan(): ?\App\Models\Plan
    {
        return $this->subscription()?->plan;
    }
}
