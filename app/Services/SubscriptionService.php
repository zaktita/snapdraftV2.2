<?php

namespace App\Services;

use App\Models\User;

class SubscriptionService
{
    /**
     * Get tier limits and features.
     */
    public static function getTierLimits(string $tier): array
    {
        return match($tier) {
            'free' => [
                'credits_per_month' => 10,
                'max_projects' => 1,
                'csv_max_rows' => 10,
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
            ],
            'launch' => [
                'credits_per_month' => 100,
                'max_projects' => 1,
                'csv_max_rows' => 50,
                'max_team_seats' => 1,
                'features' => [
                    'brand_dna_analysis' => true,
                    'batch_generation' => true,
                    'version_history' => false,
                    'priority_processing' => false,
                    'batch_regeneration' => false,
                    'advanced_canvas' => false,
                    'team_access' => false,
                ],
            ],
            'growth' => [
                'credits_per_month' => 350,
                'max_projects' => 3,
                'csv_max_rows' => 300,
                'max_team_seats' => 1,
                'features' => [
                    'brand_dna_analysis' => true,
                    'batch_generation' => true,
                    'version_history' => true,
                    'priority_processing' => true,
                    'batch_regeneration' => false,
                    'advanced_canvas' => true,
                    'team_access' => false,
                ],
            ],
            'scale' => [
                'credits_per_month' => 900,
                'max_projects' => 10,
                'csv_max_rows' => 1500,
                'max_team_seats' => 3,
                'features' => [
                    'brand_dna_analysis' => true,
                    'batch_generation' => true,
                    'version_history' => true,
                    'priority_processing' => true,
                    'batch_regeneration' => true,
                    'advanced_canvas' => true,
                    'team_access' => true,
                ],
            ],
            default => self::getTierLimits('free'),
        };
    }

    /**
     * Get tier pricing information.
     */
    public static function getTierPricing(string $tier): array
    {
        return match($tier) {
            'free' => [
                'monthly_price' => 0,
                'yearly_price' => 0,
                'currency' => 'EUR',
            ],
            'launch' => [
                'monthly_price' => 39,
                'yearly_price' => 31, // 20% discount
                'currency' => 'EUR',
                'yearly_savings' => 96,
            ],
            'growth' => [
                'monthly_price' => 89,
                'yearly_price' => 71, // 20% discount
                'currency' => 'EUR',
                'yearly_savings' => 216,
            ],
            'scale' => [
                'monthly_price' => 199,
                'yearly_price' => 159, // 20% discount
                'currency' => 'EUR',
                'yearly_savings' => 480,
            ],
            default => self::getTierPricing('free'),
        };
    }

    /**
     * Check if user can create a new project.
     */
    public static function canCreateProject(User $user): bool
    {
        $limits = self::getTierLimits($user->subscription_tier);
        $currentProjects = $user->projects()->count();
        
        return $currentProjects < $limits['max_projects'];
    }

    /**
     * Check if user can upload CSV with given number of rows.
     */
    public static function canUploadCsvRows(User $user, int $rows): bool
    {
        $limits = self::getTierLimits($user->subscription_tier);
        
        return $rows <= $limits['csv_max_rows'];
    }

    /**
     * Check if user has access to a specific feature.
     */
    public static function hasFeature(User $user, string $feature): bool
    {
        $limits = self::getTierLimits($user->subscription_tier);
        
        return $limits['features'][$feature] ?? false;
    }

    /**
     * Get remaining project slots for user.
     */
    public static function getRemainingProjectSlots(User $user): int
    {
        $limits = self::getTierLimits($user->subscription_tier);
        $currentProjects = $user->projects()->count();
        
        return max(0, $limits['max_projects'] - $currentProjects);
    }

    /**
     * Get tier display name.
     */
    public static function getTierDisplayName(string $tier): string
    {
        return match($tier) {
            'free' => 'Free',
            'launch' => 'Launch Plan',
            'growth' => 'Growth Plan',
            'scale' => 'Scale Plan',
            default => 'Free',
        };
    }

    /**
     * Get processing priority based on tier.
     */
    public static function getProcessingPriority(string $tier): int
    {
        return match($tier) {
            'scale' => 1,      // Highest priority
            'growth' => 2,     // High priority
            'launch' => 3,     // Normal priority
            'free' => 4,       // Low priority
            default => 5,      // Lowest priority
        };
    }

    /**
     * Calculate upgrade/downgrade prorated amount.
     */
    public static function calculateProration(User $user, string $newTier, string $billingPeriod = 'monthly'): array
    {
        $currentPricing = self::getTierPricing($user->subscription_tier);
        $newPricing = self::getTierPricing($newTier);

        $currentPrice = $billingPeriod === 'monthly' 
            ? $currentPricing['monthly_price'] 
            : $currentPricing['yearly_price'];

        $newPrice = $billingPeriod === 'monthly' 
            ? $newPricing['monthly_price'] 
            : $newPricing['yearly_price'];

        $daysInMonth = 30;
        $daysRemaining = 30; // TODO: Calculate actual days remaining

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
