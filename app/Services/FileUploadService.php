<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileUploadService
{
    /**
     * Allowed image mime types
     */
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];

    /**
     * Max file size in KB
     */
    protected int $maxFileSize = 10240; // 10MB

    /**
     * Upload an image and create thumbnail
     *
     * @param UploadedFile $file
     * @param string $directory (e.g., 'projects/1/images' or 'projects/1/references')
     * @return array ['url' => string, 'thumbnail_url' => string|null]
     */
    public function uploadImage(UploadedFile $file, string $directory): array
    {
        $this->validateImage($file);

        // Generate unique filename
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $directory . '/' . $filename;

        // Store the original file
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        // Generate thumbnail
        $thumbnailPath = $this->generateThumbnail($file, $directory, $filename);

        return [
            'url' => $path,
            'thumbnail_url' => $thumbnailPath,
            'format' => $file->getClientOriginalExtension(),
            'width' => getimagesize($file->getRealPath())[0] ?? null,
            'height' => getimagesize($file->getRealPath())[1] ?? null,
        ];
    }

    /**
     * Upload multiple images
     *
     * @param array $files
     * @param string $directory
     * @return array
     */
    public function uploadMultipleImages(array $files, string $directory): array
    {
        $uploadedFiles = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $uploadedFiles[] = $this->uploadImage($file, $directory);
            }
        }

        return $uploadedFiles;
    }

    /**
     * Generate a thumbnail for the image
     *
     * @param UploadedFile $file
     * @param string $directory
     * @param string $filename
     * @return string|null
     */
    protected function generateThumbnail(UploadedFile $file, string $directory, string $filename): ?string
    {
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file->getRealPath());

            // Resize to thumbnail (400x400 max, maintaining aspect ratio)
            $image->scale(width: 400);

            // Save thumbnail
            $thumbnailFilename = 'thumb_' . $filename;
            $thumbnailPath = $directory . '/' . $thumbnailFilename;
            
            $thumbnailFullPath = storage_path('app/public/' . $thumbnailPath);
            
            // Ensure directory exists
            $thumbnailDir = dirname($thumbnailFullPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            $image->save($thumbnailFullPath, quality: 85);

            return $thumbnailPath;
        } catch (\Exception $e) {
            // If thumbnail generation fails, return null
            // The full image will be used as fallback
            \Log::warning('Thumbnail generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate the uploaded image
     *
     * @param UploadedFile $file
     * @throws \InvalidArgumentException
     */
    protected function validateImage(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize * 1024) {
            throw new \InvalidArgumentException('File size exceeds maximum allowed size of ' . $this->maxFileSize . 'KB');
        }

        // Check mime type
        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException('Invalid file type. Only JPG, PNG, and WebP images are allowed.');
        }

        // Check if file is actually an image
        if (!getimagesize($file->getRealPath())) {
            throw new \InvalidArgumentException('The uploaded file is not a valid image.');
        }
    }

    /**
     * Delete an image and its thumbnail
     *
     * @param string $path
     * @param string|null $thumbnailPath
     * @return bool
     */
    public function deleteImage(string $path, ?string $thumbnailPath = null): bool
    {
        $deleted = Storage::disk('public')->delete($path);

        if ($thumbnailPath) {
            Storage::disk('public')->delete($thumbnailPath);
        }

        return $deleted;
    }

    /**
     * Delete multiple images
     *
     * @param array $paths Array of ['url' => string, 'thumbnail_url' => string|null]
     * @return bool
     */
    public function deleteMultipleImages(array $paths): bool
    {
        foreach ($paths as $pathData) {
            $this->deleteImage($pathData['url'], $pathData['thumbnail_url'] ?? null);
        }

        return true;
    }
}
