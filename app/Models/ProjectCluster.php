<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectCluster extends Model
{
    protected $fillable = [
        'project_id',
        'key',
        'label',
        'summary',
        'keywords_json',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'keywords_json' => 'array',
            'position' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProjectClusterImage::class)->orderBy('position');
    }
}
