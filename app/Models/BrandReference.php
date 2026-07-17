<?php

namespace App\Models;

use App\Services\UserMediaStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'url',
        'thumbnail_url',
        'analysis_data',
        'order',
    ];

    protected $casts = [
        'analysis_data' => 'array',
        'order' => 'integer',
    ];

    protected $appends = [
        'full_url',
        'thumbnail_full_url',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function getFullUrlAttribute(): ?string
    {
        return app(UserMediaStorage::class)->url($this->url);
    }

    public function getThumbnailFullUrlAttribute(): ?string
    {
        $path = $this->thumbnail_url ?: $this->url;

        return app(UserMediaStorage::class)->url($path);
    }

    protected static function booted(): void
    {
        static::deleted(function (BrandReference $reference) {
            $media = app(UserMediaStorage::class);

            if ($reference->url) {
                $media->delete($reference->url);
            }
            if ($reference->thumbnail_url) {
                $media->delete($reference->thumbnail_url);
            }
        });
    }
}
