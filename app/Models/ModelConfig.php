<?php

namespace App\Models;

use App\Enums\ModelCapability;
use Illuminate\Database\Eloquent\Model;

class ModelConfig extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'provider',
        'openrouter_model_id',
        'capability',
        'driver',
        'is_default',
        'is_enabled',
        'sort_order',
        'config_json',
        'cost_hint_json',
    ];

    protected function casts(): array
    {
        return [
            'capability' => ModelCapability::class,
            'is_default' => 'boolean',
            'is_enabled' => 'boolean',
            'config_json' => 'array',
            'cost_hint_json' => 'array',
        ];
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForCapability($query, ModelCapability|string $capability)
    {
        $value = $capability instanceof ModelCapability ? $capability->value : $capability;

        return $query->where('capability', $value);
    }
}
