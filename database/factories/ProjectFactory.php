<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);
        
        return [
            'user_id' => User::factory(),
            'name' => $name,
            'title' => $name, // title is required, use same as name
            'description' => fake()->sentence(),
            'format' => fake()->randomElement(['square', 'landscape', 'portrait', 'story']),
            'status' => fake()->randomElement(['draft', 'processing', 'completed', 'failed']),
            'is_favorite' => fake()->boolean(20), // 20% chance of being favorite
            'images_count' => fake()->numberBetween(0, 50),
            'created_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'updated_at' => function (array $attributes) {
                return $attributes['created_at'];
            },
        ];
    }

    /**
     * Indicate that the project is a favorite.
     */
    public function favorite(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_favorite' => true,
        ]);
    }

    /**
     * Indicate that the project is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the project is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
        ]);
    }

    /**
     * Indicate that the project has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    /**
     * Set a specific format for the project.
     */
    public function format(string $format): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => $format,
        ]);
    }

    /**
     * Set a specific number of images.
     */
    public function withImages(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'images_count' => $count,
        ]);
    }
}
