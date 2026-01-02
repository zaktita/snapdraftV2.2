<?php

namespace App\Http\Controllers;

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
                    'id' => 'starter',
                    'name' => 'Starter',
                    'subtitle' => 'The Decoy',
                    'price' => 29,
                    'credits' => 200,
                    'features' => [
                        '200 credits/month',
                        '~50 high-precision posts',
                        '1 active project',
                        'Free Brand DNA / Setup (Unlimited)',
                        '30 rows per CSV batch',
                        'Basic canvas editor',
                    ],
                ],
                [
                    'id' => 'growth',
                    'name' => 'Growth',
                    'subtitle' => 'The Anchor',
                    'price' => 59,
                    'credits' => 650,
                    'popular' => true,
                    'features' => [
                        '650 credits/month (3.2x more)',
                        '~160 high-precision posts',
                        '10 active projects',
                        'Free Brand DNA / Setup (Unlimited)',
                        'Unlimited CSV batching',
                        'Full canvas editor + Versioning',
                    ],
                ],
                [
                    'id' => 'agency',
                    'name' => 'Agency',
                    'subtitle' => 'The Pro',
                    'price' => 149,
                    'credits' => 1800,
                    'features' => [
                        '1,800 credits/month (9x more)',
                        '~450 high-precision posts',
                        'Unlimited active projects',
                        'Free Brand DNA / Setup (Unlimited)',
                        'Priority queue CSV batching',
                        'Full canvas editor + Teams',
                    ],
                ],
            ];

        return Inertia::render('subscription/plans', [
            'plans' => $plans,
            'current_tier' => $user->subscription_tier,
            'credits_remaining' => $user->credits_remaining,
            'credits_total' => $user->credits_total,
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
     * Upgrade subscription (Stripe integration placeholder).
     */
    public function upgrade(Request $request)
    {
        $request->validate([
              'tier' => 'required|in:pro,enterprise',
        ]);

        $user = Auth::user();

        // TODO: Integrate with Stripe
        // For now, just update the database
        $user->update([
              'subscription_tier' => $request->tier,
            'subscription_started_at' => now(),
            'subscription_ends_at' => now()->addMonth(),
        ]);

        $user->resetMonthlyCredits();

        return back()->with('success', 'Subscription upgraded successfully!');
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
