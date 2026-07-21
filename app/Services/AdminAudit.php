<?php

namespace App\Services;

use App\Models\AdminAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AdminAudit
{
    /**
     * Persist a queryable admin action and mirror it to the application log.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function record(string $action, ?Model $subject = null, array $metadata = []): void
    {
        $adminId = Auth::id();

        try {
            AdminAction::create([
                'admin_id' => $adminId,
                'action' => $action,
                'subject_type' => $subject ? $subject->getMorphClass() : null,
                'subject_id' => $subject?->getKey(),
                'metadata' => $metadata === [] ? null : $metadata,
                'ip_address' => Request::ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[admin] Failed to persist audit row', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('[admin] '.$action, array_filter([
            'admin_id' => $adminId,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]));
    }
}
