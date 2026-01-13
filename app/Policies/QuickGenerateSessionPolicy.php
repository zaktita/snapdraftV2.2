<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\QuickGenerateSession;
use App\Models\User;

class QuickGenerateSessionPolicy
{
    /**
     * Determine if the user can view the session.
     */
    public function view(User $user, QuickGenerateSession $session): bool
    {
        // User can view if they own the associated project
        return $session->project->user_id === $user->id;
    }
}
