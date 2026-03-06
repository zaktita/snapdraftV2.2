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
        // Deactivate any old plans (Launch / Growth / Scale) so they no longer appear
        Plan::whereIn('slug', ['launch', 'growth', 'scale'])->update(['is_active' => false]);

        // Single beta plan — variant IDs come from .env
        $betaMonthly = config('services.lemonsqueezy.variants.beta_monthly') ?: 'BETA_MONTHLY_VARIANT_ID';
        $betaYearly  = config('services.lemonsqueezy.variants.beta_yearly')  ?: 'BETA_YEARLY_VARIANT_ID';

        $plans = [
            [
                'provider' => 'lemonsqueezy',
                'provider_product_id' => $betaMonthly,
                'provider_variant_monthly' => $betaMonthly,
                'provider_variant_yearly' => $betaYearly,
                'name' => 'SnapDraft Beta',
                'slug' => 'beta',
                'description' => 'Full access to SnapDraft during the beta period. Generate brand-consistent visuals from CSV data with AI.',
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
                        'team_access' => false,
                    ],
                ],
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'has_trial' => true,
                'trial_days' => 7,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }

        $this->command->info('Plans seeded successfully!');
        $this->command->info('1 plan created: SnapDraft Beta ($29/mo, 100 credits, 10 projects, 7-day trial)');
    }
}
