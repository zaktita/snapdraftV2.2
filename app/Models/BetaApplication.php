<?php

namespace App\Models;

use App\Enums\BetaApplicationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BetaApplication extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'email',
        'role',
        'monthly_post_volume',
        'visual_workflow',
        'status',
        'invite_code',
        'beta_invite_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => BetaApplicationStatus::class,
        ];
    }

    public function betaInvite(): BelongsTo
    {
        return $this->belongsTo(BetaInvite::class);
    }
}
