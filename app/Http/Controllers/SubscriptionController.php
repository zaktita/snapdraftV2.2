<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PostHogService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    /**
     * Display subscription plans.
     */
    public function index(): Response
    {
        try {
            $user = Auth::user();
            
            // Get active public plans (exclude invite-only beta)
            $dbPlans = Plan::active()
                ->ordered()
                ->get()
                ->filter(function (Plan $plan) {
                    $features = $plan->capabilities['features'] ?? [];
                    if (($features['is_public'] ?? true) === false) {
                        return false;
                    }

                    return $plan->slug !== 'beta';
                });

            // Transform plans for frontend
            $plans = $dbPlans->groupBy('slug')->map(function ($planGroup) {
                $plan = $planGroup->first();
                $capabilities = $plan->capabilities ?? [];
                // Guard: if capabilities is a JSON string (double-encoded), decode it
                if (is_string($capabilities)) {
                    $capabilities = json_decode($capabilities, true) ?? [];
                }

                // Get pricing for both periods
                $pricing = SubscriptionService::getTierPricing($plan->slug);

                return [
                    'id' => $plan->slug,
                    'name' => $plan->name,
                    'subtitle' => $this->getSubtitle($plan->slug),
                    'price' => (float) $plan->price,
                    'yearly_price' => (float) $pricing['yearly_price'],
                    'yearly_total' => (float) ($pricing['yearly_total'] ?? ($plan->price * 10)),
                    'currency' => $plan->currency,
                    'credits' => (int) ($capabilities['credits_per_month'] ?? 0),
                    'max_projects' => (int) ($capabilities['max_projects'] ?? 0),
                    'csv_max_rows' => (int) ($capabilities['csv_max_rows'] ?? 0),
                    'popular' => (bool) $plan->is_featured,
                    'features' => $this->formatFeatures($capabilities),
                    'bestFor' => $this->getBestFor($plan->slug),
                ];
            })->values()->toArray();

            // Get current user's subscription info
            $subscription = $user->subscription();
            $currentTier = $subscription?->name ?? null;
            $creditsRemaining = $subscription ? $subscription->creditsRemaining() : 0;
            $creditsTotal = $subscription ? $subscription->creditsLimit() : 0;
            $remainingSlots = SubscriptionService::getRemainingProjectSlots($user);

            return Inertia::render('subscription/plans', [
                'plans' => $plans,
                'current_tier' => $currentTier,
                'credits_remaining' => $creditsRemaining,
                'credits_total' => $creditsTotal,
                'remaining_project_slots' => $remainingSlots,
            ]);
        } catch (\Exception $e) {
            Log::error('Subscription plans error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return minimal data on error
            return Inertia::render('subscription/plans', [
                'plans' => [],
                'current_tier' => null,
                'credits_remaining' => 0,
                'credits_total' => 0,
                'remaining_project_slots' => 0,
            ]);
        }
    }

    /**
     * Get subtitle for plan.
     */
    private function getSubtitle(string $slug): string
    {
        return match($slug) {
            'starter'  => 'Brand-consistent posts from CSV',
            'pro'      => 'Scale campaigns without waiting',
            'business' => 'High-volume brands & agencies',
            'beta'     => 'Invite-only beta access',
            default => '',
        };
    }

    /**
     * Format capabilities into feature list.
     */
    private function formatFeatures(array $capabilities): array
    {
        $features = [];
        $flags = $capabilities['features'] ?? [];

        if (isset($capabilities['credits_per_month'])) {
            $features[] = $capabilities['credits_per_month'] . ' Production Credits / month';
        }

        if (isset($capabilities['max_projects'])) {
            $features[] = $capabilities['max_projects'] . ' Brand Project' . ($capabilities['max_projects'] > 1 ? 's' : '');
        }

        if (isset($capabilities['csv_max_rows'])) {
            $features[] = 'CSV upload up to ' . number_format($capabilities['csv_max_rows']) . ' rows';
        }

        if (isset($capabilities['max_team_seats']) && $capabilities['max_team_seats'] > 1) {
            $features[] = 'Team access (' . $capabilities['max_team_seats'] . ' seats included)';
        }

        if ($flags['priority_processing'] ?? false) {
            $features[] = 'Priority processing';
        }

        if ($flags['batch_regeneration'] ?? false) {
            $features[] = 'Batch regeneration';
        }

        if ($flags['advanced_canvas'] ?? false) {
            if ($flags['version_history'] ?? false) {
                $features[] = 'Advanced Canvas Editor + Version history';
            } else {
                $features[] = 'Advanced Canvas Editor';
            }
        } elseif ($flags['basic_canvas'] ?? false) {
            $features[] = 'Basic Canvas Editor';
        }

        if ($flags['batch_generation'] ?? false) {
            $features[] = 'Full Batch Generation';
        }

        if ($flags['brand_dna_analysis'] ?? false) {
            $features[] = 'Brand DNA extraction';
        }

        if (($flags['image_generation'] ?? false) && ($flags['text_generation'] ?? false) && !($flags['batch_generation'] ?? false)) {
            $features[] = 'Image + Text generation';
        }

        if ($flags['standard_processing'] ?? false) {
            $features[] = 'Standard processing speed';
        } elseif ($flags['priority_processing'] ?? false) {
            // already added above, skip
        }

        if ($flags['advanced_canvas'] ?? false) {
            $features[] = 'Full Canvas capabilities';
        }

        return $features;
    }

    /**
     * Get best for list.
     */
    private function getBestFor(string $slug): array
    {
        return match($slug) {
            'starter'  => ['Solo founders', 'Freelancers', 'Getting started'],
            'pro'      => ['Growing brands', 'Weekly campaigns', 'Marketing teams'],
            'business' => ['Agencies', 'High-volume brands', 'Content ops'],
            'beta'     => ['Early adopters'],
            default => [],
        };
    }

    /**
     * Display billing portal.
     */
    public function portal(): Response
    {
        $user = Auth::user();

        // Get active subscription from subscriptions table
        $activeSubscription = $user->subscription();
        
        $subscription = null;
        if ($activeSubscription) {
            $subscription = [
                'id' => $activeSubscription->id,
                'tier' => $activeSubscription->name,
                'billing_period' => $activeSubscription->billing_period,
                'status' => $activeSubscription->status,
                'price' => $activeSubscription->price,
                'currency' => $activeSubscription->currency,
                'credits_remaining' => $activeSubscription->creditsRemaining(),
                'credits_total' => $activeSubscription->creditsLimit(),
                'started_at' => $activeSubscription->created_at,
                'renews_at' => $activeSubscription->renews_at,
                'ends_at' => $activeSubscription->ends_at,
                'cancelled_at' => $activeSubscription->cancelled_at,
                'auto_renew' => !$activeSubscription->cancelled_at,
                'customer_portal_url' => $activeSubscription->customer_portal_url,
                'update_payment_url' => $activeSubscription->update_payment_url,
                'on_trial' => $activeSubscription->onTrial(),
            ];
        }

        // Get all user subscriptions (history)
        $subscriptionHistory = $user->subscriptions()
            ->latest()
            ->limit(10)
            ->get()
            ->map(function($sub) {
                return [
                    'id' => $sub->id,
                    'tier' => $sub->name,
                    'status' => $sub->status,
                    'billing_period' => $sub->billing_period,
                    'started_at' => $sub->created_at,
                    'ended_at' => $sub->ends_at,
                ];
            });

        return Inertia::render('subscription/portal', [
            'subscription' => $subscription,
            'subscription_history' => $subscriptionHistory,
        ]);
    }

    /**
     * Create checkout session for subscription upgrade.
     */
    public function upgrade(Request $request)
    {
        Log::info('🚀 Subscription upgrade request received', [
            'tier' => $request->input('tier'),
            'billing_period' => $request->input('billing_period'),
            'user_id' => Auth::id(),
        ]);

        $request->validate([
            'tier' => ['required', 'string', 'in:starter,pro,business'],
            'billing_period' => 'required|in:monthly,yearly',
        ]);

        $user = Auth::user();
        $tier = $request->tier;
        $billingPeriod = $request->billing_period;

        Log::info('📋 Processing upgrade', [
            'tier' => $tier,
            'billing_period' => $billingPeriod,
            'user_email' => $user->email,
        ]);

        // Get the plan from database
        $plan = Plan::where('slug', $tier)->first();
        
        if (!$plan) {
            Log::error('❌ Plan not found', ['tier' => $tier]);
            return back()->with('error', 'Invalid subscription plan selected.');
        }

        Log::info('✅ Plan found', [
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'monthly_variant' => $plan->provider_variant_monthly,
            'yearly_variant' => $plan->provider_variant_yearly,
        ]);

        // Get the variant ID based on billing period
        $variantId = $billingPeriod === 'yearly' 
            ? $plan->provider_variant_yearly 
            : $plan->provider_variant_monthly;
        
        Log::info('🎯 Selected variant', [
            'billing_period' => $billingPeriod,
            'variant_id' => $variantId,
        ]);
        
        // Reject missing or placeholder IDs left by PlanSeeder when .env variants were empty
        if (!$variantId || !ctype_digit((string) $variantId)) {
            Log::error('❌ Variant ID not configured or invalid', [
                'tier' => $tier,
                'billing_period' => $billingPeriod,
                'variant_id' => $variantId,
            ]);
            return back()->with('error', 'Plan variant not configured. Please contact support.');
        }

        // Check if API keys are configured
        $storeId = config('services.lemonsqueezy.store_id');
        $apiKey = config('services.lemonsqueezy.api_key');
        
        Log::info('🔑 Checking API configuration', [
            'has_store_id' => !empty($storeId),
            'has_api_key' => !empty($apiKey),
            'store_id' => $storeId,
        ]);
        
        if (empty($storeId) || empty($apiKey)) {
            Log::info('Demo mode: Subscription upgrade requested (missing API keys)', [
                'user_id' => $user->id,
                'tier' => $tier,
                'billing_period' => $billingPeriod,
            ]);
            
            return back()->with('info', '🎨 Demo Mode: Configure Lemon Squeezy API keys in .env to enable live payments.');
        }

        try {
            $httpClient = Http::withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer ' . $apiKey,
            ]);

            // Disable SSL verification only when explicitly configured (e.g. local XAMPP dev)
            if (!config('services.lemonsqueezy.verify_ssl', true)) {
                $httpClient = $httpClient->withOptions(['verify' => false]);
            }

            $response = $httpClient->post('https://api.lemonsqueezy.com/v1/checkouts', [
                'data' => [
                    'type' => 'checkouts',
                    'attributes' => [
                        'checkout_data' => [
                            'email' => $user->email,
                            'name' => $user->name,
                            'custom' => [
                                'user_id' => (string) $user->id,
                                'tier' => $tier,
                                'billing_period' => $billingPeriod,
                            ],
                        ],
                    ],
                    'relationships' => [
                        'store' => [
                            'data' => [
                                'type' => 'stores',
                                'id' => $storeId,
                            ],
                        ],
                        'variant' => [
                            'data' => [
                                'type' => 'variants',
                                'id' => $variantId,
                            ],
                        ],
                    ],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('Lemon Squeezy checkout failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'variant_id' => $variantId,
                    'store_id' => $storeId,
                    'tier' => $tier,
                ]);
                $detail = data_get($response->json(), 'errors.0.detail');
                return back()->with('error', $detail
                    ? "Checkout failed: {$detail}"
                    : 'Failed to create checkout session. Please try again.');
            }

            $checkoutUrl = $response->json('data.attributes.url');

            if (empty($checkoutUrl)) {
                return back()->with('error', 'Failed to create checkout session. Please try again.');
            }

            app(PostHogService::class)->capture((string) $user->id, 'upgrade_checkout_started', [
                'plan'           => $tier,
                'billing_period' => $billingPeriod,
            ]);

            // Redirect to Lemon Squeezy checkout
            return Inertia::location($checkoutUrl);
        } catch (\Exception $e) {
            Log::error('Lemon Squeezy checkout error', [
                'user_id' => $user->id,
                'tier' => $tier,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to create checkout session. Please try again.');
        }
    }

    /**
     * Cancel subscription.
     */
    public function downgrade()
    {
        $user = Auth::user();
        $subscription = $user->subscription();

        if (!$subscription) {
            return back()->with('error', 'No active subscription found.');
        }

        try {
            // Cancel Lemon Squeezy subscription via API
            $apiKey = config('services.lemonsqueezy.api_key');
            
            $httpClient = Http::withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer ' . $apiKey,
            ]);

            // Disable SSL verification only when explicitly configured (e.g. local XAMPP dev)
            if (!config('services.lemonsqueezy.verify_ssl', true)) {
                $httpClient = $httpClient->withOptions(['verify' => false]);
            }

            $response = $httpClient->delete('https://api.lemonsqueezy.com/v1/subscriptions/' . $subscription->lemonsqueezy_id);

            if ($response->successful()) {
                // Update subscription record (webhook will also handle this)
                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

                return back()->with('success', 'Subscription cancelled. You will retain access until the end of your billing period.');
            }

            return back()->with('error', 'Failed to cancel subscription. Please contact support.');
        } catch (\Exception $e) {
            Log::error('Subscription cancellation error', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'An error occurred while cancelling your subscription.');
        }
    }

    /**
     * Purchase additional credits.
     */
    public function purchaseCredits(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:1|max:1000',
        ]);

        // Credits are included with your plan and reset automatically on your monthly renewal date.
        // One-time credit top-up packs are not available during the beta period.
        return back()->with('info', 'Your credits reset automatically each month with your plan. One-time credit top-ups are not available during the beta period - your next batch will be available at your renewal date.');
    }
}
