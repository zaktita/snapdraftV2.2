<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed public SaaS plans + hidden Beta grandfather plan.
     */
    public function run(): void
    {
        // Deactivate legacy plan slugs
        Plan::whereIn('slug', ['launch', 'growth', 'scale'])->update(['is_active' => false]);

        $variants = config('services.lemonsqueezy.variants', []);

        $plans = [
            [
                'provider' => 'lemonsqueezy',
                'provider_product_id' => $variants['starter_monthly'] ?? 'STARTER_MONTHLY_VARIANT_ID',
                'provider_variant_monthly' => $variants['starter_monthly'] ?? 'STARTER_MONTHLY_VARIANT_ID',
                'provider_variant_yearly' => $variants['starter_yearly'] ?? 'STARTER_YEARLY_VARIANT_ID',
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Brand-consistent visuals from CSV — perfect for solo founders and freelancers.',
                'price' => 29.00,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'capabilities' => [
                    'credits_per_month' => 100,
                    'max_projects' => 10,
                    'csv_max_rows' => 25,
                    'max_team_seats' => 1,
                    'features' => [
                        'brand_dna_analysis' => true,
                        'batch_generation' => true,
                        'version_history' => true,
                        'priority_processing' => false,
                        'batch_regeneration' => false,
                        'advanced_canvas' => true,
                        'generate_more_limited' => true,
                        'team_access' => false,
                        'is_public' => true,
                    ],
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'has_trial' => false,
                'trial_days' => 0,
            ],
            [
                'provider' => 'lemonsqueezy',
                'provider_product_id' => $variants['pro_monthly'] ?? 'PRO_MONTHLY_VARIANT_ID',
                'provider_variant_monthly' => $variants['pro_monthly'] ?? 'PRO_MONTHLY_VARIANT_ID',
                'provider_variant_yearly' => $variants['pro_yearly'] ?? 'PRO_YEARLY_VARIANT_ID',
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Scale campaigns with priority processing and full regeneration.',
                'price' => 49.00,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'capabilities' => [
                    'credits_per_month' => 200,
                    'max_projects' => 30,
                    'csv_max_rows' => 50,
                    'max_team_seats' => 1,
                    'features' => [
                        'brand_dna_analysis' => true,
                        'batch_generation' => true,
                        'version_history' => true,
                        'priority_processing' => true,
                        'batch_regeneration' => true,
                        'advanced_canvas' => true,
                        'generate_more_full' => true,
                        'team_access' => false,
                        'is_public' => true,
                    ],
                ],
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'has_trial' => false,
                'trial_days' => 0,
            ],
            [
                'provider' => 'lemonsqueezy',
                'provider_product_id' => $variants['business_monthly'] ?? 'BUSINESS_MONTHLY_VARIANT_ID',
                'provider_variant_monthly' => $variants['business_monthly'] ?? 'BUSINESS_MONTHLY_VARIANT_ID',
                'provider_variant_yearly' => $variants['business_yearly'] ?? 'BUSINESS_YEARLY_VARIANT_ID',
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'High-volume brands and agencies — maximum credits and founder support.',
                'price' => 99.00,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'capabilities' => [
                    'credits_per_month' => 500,
                    'max_projects' => 100,
                    'csv_max_rows' => 150,
                    'max_team_seats' => 1,
                    'features' => [
                        'brand_dna_analysis' => true,
                        'batch_generation' => true,
                        'version_history' => true,
                        'priority_processing' => true,
                        'batch_regeneration' => true,
                        'advanced_canvas' => true,
                        'generate_more_full' => true,
                        'founder_support' => true,
                        'team_access' => false,
                        'is_public' => true,
                    ],
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'has_trial' => false,
                'trial_days' => 0,
            ],
            // Grandfather / invite-only — never listed on public pricing
            [
                'provider' => 'invite_code',
                'provider_product_id' => $variants['beta_monthly'] ?? 'BETA_MONTHLY_VARIANT_ID',
                'provider_variant_monthly' => $variants['beta_monthly'] ?? 'BETA_MONTHLY_VARIANT_ID',
                'provider_variant_yearly' => $variants['beta_yearly'] ?? 'BETA_YEARLY_VARIANT_ID',
                'name' => 'SnapDraft Beta',
                'slug' => 'beta',
                'description' => 'Invite-only beta access. Expires after 1 month or when credits run out.',
                'price' => 0,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'capabilities' => [
                    'credits_per_month' => 100,
                    'max_projects' => 10,
                    'csv_max_rows' => 25,
                    'max_team_seats' => 1,
                    'features' => [
                        'brand_dna_analysis' => true,
                        'batch_generation' => true,
                        'version_history' => true,
                        'priority_processing' => false,
                        'batch_regeneration' => false,
                        'advanced_canvas' => true,
                        'team_access' => false,
                        'is_public' => false,
                    ],
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 99,
                'has_trial' => false,
                'trial_days' => 0,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }

        $this->command?->info('Plans seeded: Starter ($29/100), Pro ($49/200), Business ($99/500), Beta (invite-only).');
    }
}
