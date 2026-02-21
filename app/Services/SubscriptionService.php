<?php

namespace App\Services;

use App\Models\User;
use App\Models\Plan;

class SubscriptionService
{
    /**
     * Get tier limits and features from database Plan model.
     * Falls back to free tier defaults if plan not found.
     */
    public static function getTierLimits(string $tier): array
    {
        $plan = Plan::where('slug', $tier)->first();
        
        if (!$plan) {
            // Free tier fallback (hardcoded since it's not a paid plan)
            return [
                'credits_per_month' => 0,
                'max_projects' => 0,
                'csv_max_rows' => 0,
                'max_team_seats' => 1,
                'features' => [
                    'brand_dna_analysis' => false,
                    'batch_generation' => false,
                    'version_history' => false,
                    'priority_processing' => false,
                    'batch_regeneration' => false,
                    'advanced_canvas' => false,
                    'team_access' => false,
                ],
            ];
        }

        return $plan->capabilities ?? [];
    }

    /**
     * Get tier pricing information from database.
     */
    public static function getTierPricing(string $tier): array
    {
        // Monthly plan
        $monthlyPlan = Plan::where('slug', $tier)->first();
        
        if (!$monthlyPlan) {
            return [
                'monthly_price' => 0,
                'yearly_price' => 0,
                'currency' => 'EUR',
            ];
        }

        // Yearly price = monthly × 10 (2 months free)
        $yearlyTotal = round($monthlyPlan->price * 10, 2);
        $yearlySavings = round($monthlyPlan->price * 2, 2); // 2 months free

        return [
            'monthly_price'    => $monthlyPlan->price,
            'yearly_price'     => round($yearlyTotal / 12, 2), // Per month when billed yearly
            'yearly_total'     => $yearlyTotal,
            'yearly_savings'   => $yearlySavings,
            'currency'         => $monthlyPlan->currency,
        ];
    }

    /**
     * Check if user can create a new project.
     */
    public static function canCreateProject(User $user): bool
    {
        $subscription = $user->subscription();
        if (!$subscription) {
            return false;
        }

        return $subscription->canCreateProject();
    }

    /**
     * Check if user can upload CSV with given number of rows.
     */
    public static function canUploadCsvRows(User $user, int $rows): bool
    {
        $subscription = $user->subscription();
        if (!$subscription) {
            return false;
        }

        $csvLimit = (int) ($subscription->capabilities['csv_rows_limit'] ?? 0);
        return $rows <= $csvLimit;
    }

    /**
     * Check if user has access to a specific feature.
     */
    public static function hasFeature(User $user, string $feature): bool
    {
        $subscription = $user->subscription();
        if (!$subscription) {
            return false;
        }

        return $subscription->hasFeature($feature);
    }

    /**
     * Get remaining project slots for user.
     */
    public static function getRemainingProjectSlots(User $user): int
    {
        $subscription = $user->subscription();
        if (!$subscription) {
            return 0;
        }

        $currentProjects = $user->projects()->count();
        $maxProjects = $subscription->projectsLimit();
        
        return max(0, $maxProjects - $currentProjects);
    }

    /**
     * Get tier display name.
     */
    public static function getTierDisplayName(string $tier): string
    {
        $plan = Plan::where('slug', $tier)->first();
        return $plan ? $plan->name : 'No Plan';
    }

    /**
     * Get processing priority based on tier.
     */
    public static function getProcessingPriority(string $tier): int
    {
        return match($tier) {
            'scale', 'scale-plan' => 1,      // Highest priority
            'growth', 'growth-plan' => 2,    // High priority
            'launch', 'launch-plan' => 3,    // Normal priority
            default => 4,                    // Normal priority (for free/unknown)
        };
    }

    /**
     * Calculate upgrade/downgrade prorated amount.
     */
    public static function calculateProration(User $user, string $newTier, string $billingPeriod = 'monthly'): array
    {
        $subscription = $user->subscription();
        if (!$subscription) {
            return [
                'current_prorated' => 0,
                'new_prorated' => 0,
                'amount_due' => 0,
                'is_upgrade' => false,
                'days_remaining' => 0,
            ];
        }

        $currentTier = $subscription->name;
        $currentPricing = self::getTierPricing($currentTier);
        $newPricing = self::getTierPricing($newTier);

        $currentPrice = $billingPeriod === 'monthly' 
            ? $currentPricing['monthly_price'] 
            : $currentPricing['yearly_price'];

        $newPrice = $billingPeriod === 'monthly' 
            ? $newPricing['monthly_price'] 
            : $newPricing['yearly_price'];

        // Calculate days remaining in current billing cycle
        $now = now();
        $nextBillingAt = $subscription->next_billing_at ?? $subscription->renews_at;
        $daysRemaining = $nextBillingAt ? $now->diffInDays($nextBillingAt) : 30;
        $daysInMonth = 30;

        $currentProrated = ($currentPrice / $daysInMonth) * $daysRemaining;
        $newProrated = ($newPrice / $daysInMonth) * $daysRemaining;
        $amountDue = max(0, $newProrated - $currentProrated);

        return [
            'current_prorated' => round($currentProrated, 2),
            'new_prorated' => round($newProrated, 2),
            'amount_due' => round($amountDue, 2),
            'is_upgrade' => $newPrice > $currentPrice,
            'days_remaining' => $daysRemaining,
        ];
    }
}
