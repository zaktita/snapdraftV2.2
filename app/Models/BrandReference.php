<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BrandReference extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'url',
        'thumbnail_url',
        'analysis_data',
        'order',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'analysis_data' => 'array',
        'order' => 'integer',
    ];

    /**
     * Get the project that owns the brand reference.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the full URL for the brand reference image.
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
        // Delete the actual files from storage when deleted
        static::deleted(function (BrandReference $reference) {
            if ($reference->url) {
                Storage::disk('public')->delete($reference->url);
            }
            if ($reference->thumbnail_url) {
                Storage::disk('public')->delete($reference->thumbnail_url);
            }
        });
    }
}