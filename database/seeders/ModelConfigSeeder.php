<?php

namespace Database\Seeders;

use App\Enums\ModelCapability;
use App\Models\ModelConfig;
use Illuminate\Database\Seeder;

class ModelConfigSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            [
                'slug' => 'gpt-4o',
                'name' => 'GPT-4o',
                'openrouter_model_id' => 'openai/gpt-4o',
                'capability' => ModelCapability::Analyze,
                'driver' => 'openrouter_chat',
                'is_default' => true,
                'sort_order' => 10,
                'config_json' => ['max_tokens' => 8192, 'temperature' => 0.2],
            ],
            [
                'slug' => 'nano-banana-2',
                'name' => 'Nano Banana 2',
                'openrouter_model_id' => 'google/gemini-3.1-flash-image-preview',
                'capability' => ModelCapability::Generate,
                'driver' => 'openrouter_image',
                'is_default' => true,
                'sort_order' => 10,
                'config_json' => ['image_size' => '2K', 'aspect_ratio' => '1:1'],
                'cost_hint_json' => ['per_image' => 0.025],
            ],
        ];

        foreach ($models as $model) {
            ModelConfig::query()->updateOrCreate(
                ['slug' => $model['slug']],
                array_merge($model, [
                    'provider' => 'openrouter',
                    'is_enabled' => true,
                    'capability' => $model['capability']->value,
                ])
            );
        }
    }
}
