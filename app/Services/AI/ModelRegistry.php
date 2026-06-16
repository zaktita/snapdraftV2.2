<?php

namespace App\Services\AI;

use App\Enums\ModelCapability;
use App\Models\ModelConfig;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class ModelRegistry
{
    public function findBySlug(string $slug): ModelConfig
    {
        $model = $this->resolveSlug($slug);

        if (! $model) {
            throw new RuntimeException("Model config [{$slug}] is not available.");
        }

        return $model;
    }

    public function resolveSlug(string $slug): ?ModelConfig
    {
        return ModelConfig::query()
            ->enabled()
            ->where('slug', $slug)
            ->first();
    }

    public function defaultFor(ModelCapability $capability): ModelConfig
    {
        $model = ModelConfig::query()
            ->enabled()
            ->forCapability($capability)
            ->where('is_default', true)
            ->orderBy('sort_order')
            ->first();

        if (! $model) {
            throw new RuntimeException("No default model configured for [{$capability->value}].");
        }

        return $model;
    }

    /**
     * @return Collection<int, ModelConfig>
     */
    public function forCapability(ModelCapability $capability): Collection
    {
        return ModelConfig::query()
            ->enabled()
            ->forCapability($capability)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
