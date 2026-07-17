<?php

namespace App\Services;

use App\Models\BrandReference;
use App\Models\Image;
use App\Models\User;

class MediaAccess
{
    public function userCanAccessPath(?User $user, string $path): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $path = ltrim($path, '/');

        if (str_contains($path, '..')) {
            return false;
        }

        $ownsImage = Image::query()
            ->where(function ($query) use ($path) {
                $query->where('url', $path)
                    ->orWhere('thumbnail_url', $path);
            })
            ->whereHas('project', fn ($q) => $q->where('user_id', $user->id))
            ->exists();

        if ($ownsImage) {
            return true;
        }

        return BrandReference::query()
            ->where(function ($query) use ($path) {
                $query->where('url', $path)
                    ->orWhere('thumbnail_url', $path);
            })
            ->whereHas('project', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }
}
