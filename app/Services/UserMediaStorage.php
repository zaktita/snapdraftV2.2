<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class UserMediaStorage
{
    /**
     * Disk for new user uploads (private local or S3).
     */
    public function diskName(): string
    {
        return (string) config('filesystems.media_disk', 'media');
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /**
     * Legacy public disk - existing files before private-media migration.
     */
    public function legacyDisk(): Filesystem
    {
        return Storage::disk('public');
    }

    public function put(string $path, string $contents): bool
    {
        return $this->disk()->put($path, $contents);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path)
            || $this->legacyDisk()->exists($path);
    }

    public function get(string $path): string
    {
        if ($this->disk()->exists($path)) {
            return $this->disk()->get($path);
        }

        return $this->legacyDisk()->get($path);
    }

    public function delete(string $path): bool
    {
        $deleted = false;

        if ($this->disk()->exists($path)) {
            $deleted = $this->disk()->delete($path) || $deleted;
        }

        if ($this->legacyDisk()->exists($path)) {
            $deleted = $this->legacyDisk()->delete($path) || $deleted;
        }

        return $deleted;
    }

    public function mimeType(string $path): string
    {
        if ($this->disk()->exists($path)) {
            return $this->disk()->mimeType($path) ?? 'application/octet-stream';
        }

        return $this->legacyDisk()->mimeType($path) ?? 'application/octet-stream';
    }

    public function path(string $path): string
    {
        if ($this->disk()->exists($path)) {
            return $this->disk()->path($path);
        }

        return $this->legacyDisk()->path($path);
    }

    /**
     * Authenticated app URL (never world-readable /storage).
     */
    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/media/')) {
            return $path;
        }

        $normalized = ltrim($path, '/');

        return route('media.show', ['path' => $normalized], false);
    }
}
