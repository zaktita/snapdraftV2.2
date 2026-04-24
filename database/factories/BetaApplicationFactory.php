<?php

namespace Database\Factories;

use App\Enums\BetaApplicationStatus;
use App\Models\BetaApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BetaApplication>
 */
class BetaApplicationFactory extends Factory
{
    protected $model = BetaApplication::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => 'marketer',
            'monthly_post_volume' => '21-50',
            'visual_workflow' => fake()->sentence(8),
            'status' => BetaApplicationStatus::Pending,
            'invite_code' => null,
            'beta_invite_id' => null,
        ];
    }
}
