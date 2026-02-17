<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionUsage extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'subscriptions_usage';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'subscription_id',
        'capabilities_used',
        'capabilities_remaining',
        'last_updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capabilities_used' => 'integer',
        'capabilities_remaining' => 'integer',
        'last_updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the usage record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription for this usage record.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Increment the usage count.
     */
    public function incrementUsage(int $amount = 1): void
    {
        $this->increment('capabilities_used', $amount);
        $this->decrement('capabilities_remaining', $amount);
        $this->update(['last_updated_at' => now()]);
    }

    /**
     * Decrement the usage count (e.g., for refunds).
     */
    public function decrementUsage(int $amount = 1): void
    {
        $this->decrement('capabilities_used', $amount);
        $this->increment('capabilities_remaining', $amount);
        $this->update(['last_updated_at' => now()]);
    }

    /**
     * Reset the usage for a new billing cycle.
     */
    public function resetUsage(int $newLimit): void
    {
        $this->update([
            'capabilities_used' => 0,
            'capabilities_remaining' => $newLimit,
            'last_updated_at' => now(),
        ]);
    }

    /**
     * Check if there are capabilities remaining.
     */
    public function hasCapabilitiesRemaining(): bool
    {
        return $this->capabilities_remaining > 0;
    }

    /**
     * Get the usage percentage.
     */
    public function usagePercentage(): float
    {
        $total = $this->capabilities_used + $this->capabilities_remaining;
        
        if ($total === 0) {
            return 0;
        }

        return ($this->capabilities_used / $total) * 100;
    }
}
