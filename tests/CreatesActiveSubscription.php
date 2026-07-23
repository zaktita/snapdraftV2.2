<?php

namespace Tests;

use App\Models\Plan;
use App\Models\User;

trait CreatesActiveSubscription
{
    protected function createUserWithSubscription(array $userAttributes = []): User
    {
        $user = User::factory()->create($userAttributes);

        $plan = Plan::query()->where('slug', 'starter')->first()
            ?? Plan::create([
                'provider' => 'test',
                'name' => 'Launch',
                'slug' => 'starter',
                'price' => 29,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'capabilities' => [
                    'credits_per_month' => 100,
                    'max_projects' => 10,
                    'csv_max_rows' => 25,
                ],
                'is_active' => true,
            ]);

        $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'provider' => 'test',
            'billing_period' => 'monthly',
            'starts_at' => now(),
            'price' => 0,
            'amount_paid' => 0,
            'currency' => 'USD',
            'capabilities' => [
                'credits_remaining' => 100,
                'credits_limit' => 100,
                'credits_used' => 0,
                'credits_per_month' => 100,
                'max_projects' => 10,
                'csv_max_rows' => 25,
            ],
        ]);

        return $user;
    }
}
