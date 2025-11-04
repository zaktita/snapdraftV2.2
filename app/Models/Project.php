<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'name',
        'title',
        'description',
        'format',
        'status',
        'settings',
        'is_favorite',
        'featured_image',
        'images_count',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'settings' => 'array',
        'is_favorite' => 'boolean',
        'images_count' => 'integer',
    ];

    /**
     * Get the user that owns the project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the images for the project.
     */
    public function images(): HasMany
    {
        return $this->hasMany(Image::class)->orderBy('order');
    }

    /**
     * Get the brand references for the project.
     */
    public function brandReferences(): HasMany
    {
        return $this->hasMany(BrandReference::class)->orderBy('order');
    }

    /**
     * Get the generation history for the project.
     */
    public function generationHistory(): HasMany
    {
        return $this->hasMany(GenerationHistory::class);
    }

    /**
     * Scope a query to only include favorite projects.
     */
    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Scope a query to only include recent projects (last 7 days).
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDays(7));
    }

    /**
     * Update the cached images count.
     */
    public function updateImagesCount(): void
    {
        $this->update(['images_count' => $this->images()->count()]);
    }

    /**
     * Update the featured image to the first image.
     */
    public function updateFeaturedImage(): void
    {
        $firstImage = $this->images()->first();
        $this->update([
            'featured_image' => $firstImage?->thumbnail_url ?? $firstImage?->url,
        ]);
    }
}