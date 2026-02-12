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
        'subscription_tier',
        'credits_remaining',
        'credits_total',
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
        'stripe_customer_id',
        'stripe_subscription_id',
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
            'subscription_started_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
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
        return $this->credits_remaining >= $amount;
    }

    /**
     * Decrement user credits.
     * 
     * @param int $amount Number of credits to deduct (default 1, or 4 for text-accurate)
     */
    public function useCredit(int $amount = 1): void
    {
        if ($this->credits_remaining >= $amount) {
            $this->decrement('credits_remaining', $amount);
            $this->increment('total_generations');
            $this->update(['last_generation_at' => now()]);
        }
    }

    /**
     * Refund user credits.
     * 
     * @param int $amount Number of credits to refund
     */
    public function refundCredit(int $amount = 1): void
    {
        $this->increment('credits_remaining', $amount);
        $this->decrement('total_generations');
    }

    /**
     * Reset monthly credits based on subscription tier.
     */
    public function resetMonthlyCredits(): void
    {
        $credits = match($this->subscription_tier) {
            'free' => 10,
            'launch' => 100,
            'growth' => 350,
            'scale' => 900,
            default => 10,
        };

        $this->update([
            'credits_remaining' => $credits,
            'credits_total' => $credits,
        ]);
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
}
