<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickGenerateSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'caption',
        'format',
        'status',
        'brand_analysis_data',
        'extracted_title',
        'extracted_description',
        'selected_cluster_id',
        'selected_image_indices',
        'final_prompt',
        'error_message',
    ];

    protected $casts = [
        'brand_analysis_data' => 'array',
        'selected_image_indices' => 'array',
    ];

    /**
     * Get the user that owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the project associated with this session.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Check if session is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if session has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if session is still processing.
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'analyzing', 'generating']);
    }

    /**
     * Mark session as analyzing.
     */
    public function markAsAnalyzing(): void
    {
        $this->update(['status' => 'analyzing']);
    }

    /**
     * Mark session as generating.
     */
    public function markAsGenerating(): void
    {
        $this->update(['status' => 'generating']);
    }

    /**
     * Mark session as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Mark session as failed with error message.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}
