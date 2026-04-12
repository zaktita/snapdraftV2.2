<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvWizardSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'status',
        'batch_id',
        'total_jobs',
        'error_message',
    ];

    protected $casts = [
        'total_jobs' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'analyzing', 'generating'], true);
    }

    public function markAsGenerating(?string $batchId = null, ?int $totalJobs = null): void
    {
        $update = ['status' => 'generating'];
        if ($batchId) {
            $update['batch_id'] = $batchId;
        }
        if (!is_null($totalJobs)) {
            $update['total_jobs'] = $totalJobs;
        }
        $this->update($update);
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}
