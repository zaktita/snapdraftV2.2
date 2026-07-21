<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\SubscriptionService;
use Illuminate\View\View;

class PricingController extends Controller
{
    public function index(): View
    {
        $plans = Plan::active()
            ->ordered()
            ->get()
            ->filter(function (Plan $plan) {
                $features = $plan->capabilities['features'] ?? [];

                return ($features['is_public'] ?? true) !== false
                    && $plan->slug !== 'beta';
            })
            ->groupBy('slug')
            ->map(function ($planGroup) {
                $plan = $planGroup->first();
                $capabilities = $plan->capabilities ?? [];
                if (is_string($capabilities)) {
                    $capabilities = json_decode($capabilities, true) ?? [];
                }

                $pricing = SubscriptionService::getTierPricing($plan->slug);

                return [
                    'id' => $plan->slug,
                    'name' => $plan->name,
                    'subtitle' => $this->subtitle($plan->slug),
                    'price' => (float) $plan->price,
                    'yearly_price' => (float) $pricing['yearly_price'],
                    'yearly_total' => (float) ($pricing['yearly_total'] ?? ($plan->price * 10)),
                    'currency' => $plan->currency,
                    'popular' => (bool) $plan->is_featured,
                    'features' => $this->features($capabilities),
                ];
            })
            ->values()
            ->toArray();

        return view('website.pricing', [
            'plans' => $plans,
        ]);
    }

    private function subtitle(string $slug): string
    {
        return match ($slug) {
            'starter' => 'For freelancers and solo social managers',
            'pro' => 'For managers shipping weekly calendars',
            'business' => 'For agencies running multiple brands',
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $capabilities
     * @return list<string>
     */
    private function features(array $capabilities): array
    {
        $features = [];
        $flags = $capabilities['features'] ?? [];

        if (isset($capabilities['credits_per_month'])) {
            $features[] = $capabilities['credits_per_month'].' production credits / month';
        }

        if (isset($capabilities['max_projects'])) {
            $features[] = $capabilities['max_projects'].' brand project'.($capabilities['max_projects'] > 1 ? 's' : '');
        }

        if (isset($capabilities['csv_max_rows'])) {
            $features[] = 'CSV upload up to '.number_format($capabilities['csv_max_rows']).' rows';
        }

        if (isset($capabilities['max_team_seats']) && $capabilities['max_team_seats'] > 1) {
            $features[] = 'Team access ('.$capabilities['max_team_seats'].' seats included)';
        }

        if ($flags['brand_dna_analysis'] ?? false) {
            $features[] = 'Brand DNA extraction';
        }

        if ($flags['batch_generation'] ?? false) {
            $features[] = 'Full batch generation';
        }

        if ($flags['advanced_canvas'] ?? false) {
            $features[] = 'Advanced Canvas Editor';
        } elseif ($flags['basic_canvas'] ?? false) {
            $features[] = 'Basic Canvas Editor';
        }

        if ($flags['priority_processing'] ?? false) {
            $features[] = 'Priority processing';
        }

        if ($flags['batch_regeneration'] ?? false) {
            $features[] = 'Batch regeneration';
        }

        return $features;
    }
}
