<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image as InterventionImage;

class CanvasController extends Controller
{
    /**
     * Save edited canvas as new image or update existing.
     */
    public function saveEdit(Request $request, string $projectId, string $imageId)
    {
        $image = Image::findOrFail($imageId);
        $this->authorize('update', $image);

        $validated = $request->validate([
            'canvas_data' => 'required|string', // Base64 encoded image
            'create_new' => 'boolean',
            'prompt' => 'nullable|string|max:1000',
        ]);

        // Decode base64 image data
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $validated['canvas_data']));

        if ($imageData === false) {
            return back()->with('error', 'Invalid image data.');
        }

        // Create intervention image from data
        $interventionImage = InterventionImage::read($imageData);

        // Generate filename
        $timestamp = time();
        $filename = 'edited_' . $timestamp . '.png';
        $directory = 'projects/' . $projectId . '/images';
        $fullPath = $directory . '/' . $filename;

        // Save full size image
        Storage::disk('public')->put($fullPath, $interventionImage->toPng()->toString());

        // Generate thumbnail (400x400)
        $thumbnailPath = $directory . '/thumbnails/' . $filename;
        $thumbnail = $interventionImage->cover(400, 400);
        Storage::disk('public')->put($thumbnailPath, $thumbnail->toPng()->toString());

        if ($validated['create_new'] ?? true) {
            // Create new image record
            $newImage = $image->project->images()->create([
                'url' => $fullPath,
                'thumbnail_url' => $thumbnailPath,
                'prompt' => $validated['prompt'] ?? 'Edited in Canvas Editor',
                'order' => $image->project->images()->max('order') + 1,
                'metadata' => [
                    'edited_from' => $imageId,
                    'edited_at' => now()->toIso8601String(),
                    'canvas_edit' => true,
                ],
            ]);

            return redirect()->route('projects.show', $projectId)
                ->with('success', 'Canvas saved as new image!');
        } else {
            // Update existing image
            // Delete old files
            if (Storage::disk('public')->exists($image->url)) {
                Storage::disk('public')->delete($image->url);
            }
            if (Storage::disk('public')->exists($image->thumbnail_url)) {
                Storage::disk('public')->delete($image->thumbnail_url);
            }

            // Update record
            $image->update([
                'url' => $fullPath,
                'thumbnail_url' => $thumbnailPath,
                'prompt' => $validated['prompt'] ?? $image->prompt,
                'metadata' => array_merge($image->metadata ?? [], [
                    'last_edited' => now()->toIso8601String(),
                    'canvas_edit' => true,
                ]),
            ]);

            return redirect()->route('projects.show', $projectId)
                ->with('success', 'Image updated successfully!');
        }
    }

    /**
     * Export canvas as downloadable image.
     */
    public function exportCanvas(Request $request, string $projectId)
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('view', $project);

        $validated = $request->validate([
            'canvas_data' => 'required|string', // Base64 encoded image
            'filename' => 'nullable|string|max:255',
            'format' => 'nullable|string|in:png,jpg,webp',
        ]);

        // Decode base64 image data
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $validated['canvas_data']));

        if ($imageData === false) {
            return back()->with('error', 'Invalid image data.');
        }

        // Create intervention image
        $interventionImage = InterventionImage::read($imageData);

        // Determine format and filename
        $format = $validated['format'] ?? 'png';
        $filename = ($validated['filename'] ?? $project->title . '_export_' . time()) . '.' . $format;

        // Generate image based on format
        $imageContent = match ($format) {
            'jpg' => $interventionImage->toJpeg(quality: 90)->toString(),
            'webp' => $interventionImage->toWebp(quality: 90)->toString(),
            default => $interventionImage->toPng()->toString(),
        };

        return response($imageContent)
            ->header('Content-Type', 'image/' . $format)
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
