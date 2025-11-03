<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'generation_history';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'project_id',
        'ai_model',
        'tokens_used',
        'cost',
        'status',
        'error_message',
        'request_data',
        'response_data',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'tokens_used' => 'integer',
        'cost' => 'decimal:4',
        'request_data' => 'array',
        'response_data' => 'array',
    ];

    /**
     * Get the user that owns the generation history.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the project associated with the generation.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Scope a query to only include completed generations.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include failed generations.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Mark the generation as completed.
     */
    public function markAsCompleted(?array $responseData = null): void
    {
        $this->update([
            'status' => 'completed',
            'response_data' => $responseData,
        ]);
    }

    /**
     * Mark the generation as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}