<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectClusterImage extends Model
{
    protected $fillable = [
        'project_cluster_id',
        'brand_reference_id',
        'is_anchor',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_anchor' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(ProjectCluster::class, 'project_cluster_id');
    }

    public function brandReference(): BelongsTo
    {
        return $this->belongsTo(BrandReference::class);
    }
}
