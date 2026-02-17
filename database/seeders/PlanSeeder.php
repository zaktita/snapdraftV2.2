<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seed subscription plans for SnapDraft.
     */
    public function run(): void
    {
        // Use config values if available, otherwise use test/placeholder IDs
        $launchMonthly = config('services.lemonsqueezy.variants.launch_monthly') ?: '123456';
        $launchYearly = config('services.lemonsqueezy.variants.launch_yearly') ?: '123457';
        $growthMonthly = config('services.lemonsqueezy.variants.growth_monthly') ?: '123458';
        $growthYearly = config('services.lemonsqueezy.variants.growth_yearly') ?: '123459';
        $scaleMonthly = config('services.lemonsqueezy.variants.scale_monthly') ?: '123460';
        $scaleYearly = config('services.lemonsqueezy.variants.scale_yearly') ?: '123461';

        $plans = [
            // Launch Plan
            [
                'provider' => 'lemonsqueezy',
                'provider_product_id' => $launchMonthly,
                'provider_variant_monthly' => $launchMonthly,
                'provider_variant_yearly' => $launchYearly,
                'name' => 'Launch Plan',
                'slug' => 'launch',
                'description' => 'Perfect for freelancers and solo founders getting started with AI-powered visual content generation.',
                'price' => 39.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly', // Primary billing cycle
                'capabilities' => [
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
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'has_trial' => false,
                'trial_days' => null,
            ],
            // Growth Plan
            [
                'provider' => 'lemonsqueezy',
                'provider_product_id' => $growthMonthly,
                'provider_variant_monthly' => $growthMonthly,
                'provider_variant_yearly' => $growthYearly,
                'name' => 'Growth Plan',
                'slug' => 'growth',
                'description' => 'Ideal for growing businesses that need more capacity and advanced features.',
                'price' => 89.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'capabilities' => [
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
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'has_trial' => false,
                'trial_days' => null,
            ],
            // Scale Plan
            [
                'provider' => 'lemonsqueezy',
                'provider_product_id' => $scaleMonthly,
                'provider_variant_monthly' => $scaleMonthly,
                'provider_variant_yearly' => $scaleYearly,
                'name' => 'Scale Plan',
                'slug' => 'scale',
                'description' => 'For agencies and enterprises requiring maximum capacity and premium features.',
                'price' => 199.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'capabilities' => [
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
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'has_trial' => false,
                'trial_days' => null,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }

        $this->command->info('Plans seeded successfully!');
        $this->command->info('3 plans created: Launch, Growth, Scale');
    }
}
