<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Get the project that owns the image.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the full URL for the image.
     */
    public function getFullUrlAttribute(): string
    {
        return Storage::url($this->url);
    }

    /**
     * Get the full URL for the thumbnail.
     */
    public function getThumbnailFullUrlAttribute(): ?string
    {
        return $this->thumbnail_url ? Storage::url($this->thumbnail_url) : null;
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        // Update project's images count when image is created
        static::created(function (Image $image) {
            $image->project->updateImagesCount();
            
            // Set as featured image if it's the first one
            if ($image->project->images_count === 1) {
                $image->project->updateFeaturedImage();
            }
        });

        // Update project's images count when image is deleted
        static::deleted(function (Image $image) {
            $image->project->updateImagesCount();
            
            // Update featured image if needed
            $image->project->updateFeaturedImage();
            
            // Delete the actual files from storage
            if ($image->url) {
                Storage::delete($image->url);
            }
            if ($image->thumbnail_url) {
                Storage::delete($image->thumbnail_url);
            }
        });
    }
}