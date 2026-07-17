<?php

namespace App\Models;

use App\Services\UserMediaStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'url',
        'thumbnail_url',
        'prompt',
        'metadata',
        'order',
        'is_favorite',
        'format',
        'width',
        'height',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
        'is_favorite' => 'boolean',
        'order' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected $appends = [
        'full_url',
        'thumbnail_full_url',
    ];

    /**
     * Get the project that owns the image.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the authenticated URL for the image.
     */
    public function getFullUrlAttribute(): ?string
    {
        return app(UserMediaStorage::class)->url($this->url);
    }

    /**
     * Get the authenticated URL for the thumbnail.
     */
    public function getThumbnailFullUrlAttribute(): ?string
    {
        $path = $this->thumbnail_url ?: $this->url;

        return app(UserMediaStorage::class)->url($path);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (Image $image) {
            $project = $image->project;
            if (! $project) {
                return;
            }
            $project->updateImagesCount();

            $project->refresh();
            if ($project->images_count === 1) {
                $project->updateFeaturedImage();
            }
        });

        static::deleted(function (Image $image) {
            $project = $image->project;
            $media = app(UserMediaStorage::class);

            if ($image->url) {
                $media->delete($image->url);
            }
            if ($image->thumbnail_url) {
                $media->delete($image->thumbnail_url);
            }

            if (! $project) {
                return;
            }
            $project->updateImagesCount();
            $project->updateFeaturedImage();
        });
    }
}