<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    /**
     * Display subscription plans.
     */
    public function index(): Response
    {
        $user = Auth::user();

            $plans = [
                [
                    'id' => 'launch',
                    'name' => 'Launch Plan',
                    'subtitle' => 'Entry / Freelancer / Testing',
                    'price' => 39, // Monthly price (yearly: €31/mo with 20% discount)
                    'credits' => 100,
                    'max_projects' => 1,
                    'csv_max_rows' => 50,
                    'features' => [
                        '100 Production Credits / month',
                        '1 Brand Project',
                        'CSV upload up to 50 rows',
                        'Image + Text generation',
                        'Basic Canvas Editor',
                        'Standard processing speed',
                    ],
                    'bestFor' => [
                        'Freelancers',
                        'Solo founders',
                        'Testing campaigns',
                    ],
                ],
                [
                    'id' => 'growth',
                    'name' => 'Growth Plan',
                    'subtitle' => 'Most Popular',
                    'price' => 89, // Monthly price (yearly: €71/mo with 20% discount)
                    'credits' => 350,
                    'max_projects' => 3,
                    'csv_max_rows' => 300,
                    'popular' => true,
                    'features' => [
                        '350 Production Credits / month',
                        '3 Brand Projects',
                        'CSV upload up to 300 rows',
                        'Full Batch Generation',
                        'Brand DNA extraction',
                        'Advanced Canvas Editor + Version history',
                        'Faster processing speed',
                    ],
                    'bestFor' => [
                        'Marketing teams',
                        'E-commerce brands',
                        'Weekly campaign production',
                    ],
                ],
                [
                    'id' => 'scale',
                    'name' => 'Scale Plan',
                    'subtitle' => 'For Agencies & Teams',
                    'price' => 199, // Monthly price (yearly: €159/mo with 20% discount)
                    'credits' => 900,
                    'max_projects' => 10,
                    'csv_max_rows' => 1500,
                    'features' => [
                        '900 Production Credits / month',
                        '10 Brand Projects',
                        'CSV upload up to 1,500 rows',
                        'Team access (3 seats included)',
                        'Priority processing',
                        'Batch regeneration',
                        'Full Canvas capabilities',
                    ],
                    'bestFor' => [
                        'Agencies',
                        'Multi-market brands',
                        'Content ops teams',
                    ],
                ],
            ];

        // Add current user's limits
        $currentLimits = SubscriptionService::getTierLimits($user->subscription_tier);
        $remainingSlots = SubscriptionService::getRemainingProjectSlots($user);

        return Inertia::render('subscription/plans', [
            'plans' => $plans,
            'current_tier' => $user->subscription_tier,
            'credits_remaining' => $user->credits_remaining,
            'credits_total' => $user->credits_total,
            'current_limits' => $currentLimits,
            'remaining_project_slots' => $remainingSlots,
        ]);
    }

    /**
     * Display billing portal.
     */
    public function portal(): Response
    {
        $user = Auth::user();

        $subscription = [
            'tier' => $user->subscription_tier,
            'credits_remaining' => $user->credits_remaining,
            'credits_total' => $user->credits_total,
            'started_at' => $user->subscription_started_at,
            'ends_at' => $user->subscription_ends_at,
              'stripe_customer_id' => $user->stripe_customer_id,
              'auto_renew' => true, // Default to true, will be managed by Paddle
        ];

        // Get recent invoices (mock data for now)
        $invoices = [];

        return Inertia::render('subscription/portal', [
            'subscription' => $subscription,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Upgrade subscription (Paddle integration placeholder).
     */
    public function upgrade(Request $request)
    {
        $request->validate([
              'tier' => 'required|in:launch,growth,scale',
              'billing_period' => 'sometimes|in:monthly,yearly',
        ]);

        $user = Auth::user();
        $newTier = $request->tier;
        $billingPeriod = $request->billing_period ?? 'monthly';

        // Calculate proration
        $proration = SubscriptionService::calculateProration($user, $newTier, $billingPeriod);

        // Get pricing
        $pricing = SubscriptionService::getTierPricing($newTier);
        $amount = $billingPeriod === 'monthly' ? $pricing['monthly_price'] : $pricing['yearly_price'];

        // TODO: Integrate with Paddle
        // - Create checkout session with calculated amount
        // - Handle proration credits
        // - Process payment

        // For now, just update the database
        $user->update([
              'subscription_tier' => $newTier,
            'subscription_started_at' => now(),
            'subscription_ends_at' => $billingPeriod === 'monthly' ? now()->addMonth() : now()->addYear(),
        ]);

        $user->resetMonthlyCredits();

        return back()->with('success', "Successfully upgraded to {$newTier}! Amount due: €{$amount}");
    }

    /**
     * Downgrade to free plan.
     */
    public function downgrade()
    {
        $user = Auth::user();

        // TODO: Cancel Stripe subscription

        $user->update([
            'subscription_tier' => 'free',
            'subscription_started_at' => now(),
            'subscription_ends_at' => null,
            'stripe_subscription_id' => null,
        ]);

        $user->resetMonthlyCredits();

        return back()->with('success', 'Subscription cancelled. You are now on the free plan.');
    }

    /**
     * Purchase additional credits.
     */
    public function purchaseCredits(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:1|max:1000',
        ]);

        $user = Auth::user();

        // TODO: Process payment via Stripe
        // For now, just add credits
        $user->increment('credits_remaining', $request->amount);
        $user->increment('credits_total', $request->amount);

        return back()->with('success', "Successfully purchased {$request->amount} credits!");
    }

    /**
     * Stripe webhook handler (placeholder).
     */
    public function webhook(Request $request)
    {
        // TODO: Implement Stripe webhook handling
        // - subscription.created
        // - subscription.updated
        // - subscription.deleted
        // - invoice.paid
        // - invoice.payment_failed

        return response()->json(['status' => 'success']);
    }
}
