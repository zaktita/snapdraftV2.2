<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class FileUploadService
{
    public function __construct(
        protected UserMediaStorage $media,
    ) {}

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
     * @return array{url: string, thumbnail_url: string|null, format: string, width: int|null, height: int|null}
     */
    public function uploadImage(UploadedFile $file, string $directory): array
    {
        $this->validateImage($file);

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $directory.'/'.$filename;

        $this->media->put($path, file_get_contents($file->getRealPath()));

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
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array<string, mixed>>
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

    protected function generateThumbnail(UploadedFile $file, string $directory, string $filename): ?string
    {
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file->getRealPath());
            $image->scale(width: 400);

            $thumbnailFilename = 'thumb_'.$filename;
            $thumbnailPath = $directory.'/'.$thumbnailFilename;

            $this->media->put($thumbnailPath, $image->toJpeg(85)->toString());

            return $thumbnailPath;
        } catch (\Exception $e) {
            \Log::warning('Thumbnail generation failed: '.$e->getMessage());

            return null;
        }
    }

    protected function validateImage(UploadedFile $file): void
    {
        if ($file->getSize() > $this->maxFileSize * 1024) {
            throw new \InvalidArgumentException('File size exceeds maximum allowed size of '.$this->maxFileSize.'KB');
        }

        if (! in_array($file->getMimeType(), $this->allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('Invalid file type. Only JPG, PNG, and WebP images are allowed.');
        }

        if (! getimagesize($file->getRealPath())) {
            throw new \InvalidArgumentException('The uploaded file is not a valid image.');
        }
    }

    public function deleteImage(string $path, ?string $thumbnailPath = null): bool
    {
        $deleted = $this->media->delete($path);

        if ($thumbnailPath) {
            $deleted = $this->media->delete($thumbnailPath) || $deleted;
        }

        return $deleted;
    }

    /**
     * @param  array<int, array{url: string, thumbnail_url?: string|null}>  $paths
     */
    public function deleteMultipleImages(array $paths): bool
    {
        foreach ($paths as $pathData) {
            $this->deleteImage($pathData['url'], $pathData['thumbnail_url'] ?? null);
        }

        return true;
    }
}
