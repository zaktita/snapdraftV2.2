<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSingleImageJob;
use App\Models\GenerationHistory;
use App\Models\Image;
use App\Models\Project;
use App\Services\PostHogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\AI\AIServiceManager;
use ZipArchive;

class ImageController extends Controller
{
    /**
     * Update the specified image.
     */
    public function update(Request $request, string $projectId, string $imageId)
    {
        $image = Image::findOrFail($imageId);
        $this->authorize('update', $image);

        $validated = $request->validate([
            'prompt' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_favorite' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $image->update($validated);

        return back()->with('success', 'Image updated successfully!');
    }

    /**
     * Remove the specified image.
     */
    public function destroy(string $projectId, string $imageId)
    {
        $image = Image::findOrFail($imageId);
        $this->authorize('delete', $image);
        
        $image->delete();

        return back()->with('success', 'Image deleted successfully!');
    }

    /**
     * Bulk delete images.
     */
    public function bulkDestroy(Request $request, string $projectId)
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('update', $project);

        $validated = $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'integer|exists:images,id',
        ]);

        // Authorize each image
        $images = $project->images()->whereIn('id', $validated['image_ids'])->get();
        foreach ($images as $image) {
            $this->authorize('delete', $image);
        }

        $project->images()->whereIn('id', $validated['image_ids'])->delete();

        return back()->with('success', count($validated['image_ids']) . ' images deleted successfully!');
    }

    /**
     * Download multiple images as ZIP.
     */
    public function bulkDownload(Request $request, string $projectId)
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('view', $project);

        $validated = $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'integer|exists:images,id',
        ]);

        $images = $project->images()->whereIn('id', $validated['image_ids'])->get();

        if ($images->isEmpty()) {
            return back()->with('error', 'No images found to download.');
        }

        // Guard against excessively large ZIP downloads (~100 MB total)
        $totalBytes = 0;
        $maxBytes   = 100 * 1024 * 1024; // 100 MB
        foreach ($images as $image) {
            $filePath = Storage::disk('public')->path($image->url);
            if (file_exists($filePath)) {
                $totalBytes += filesize($filePath);
            }
        }
        if ($totalBytes > $maxBytes) {
            return back()->with('error', 'Selected images exceed the 100 MB download limit. Please select fewer images.');
        }

        // Create a temporary file for the ZIP
        $zipFileName = 'project_' . $project->id . '_images_' . time() . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);

        // Ensure temp directory exists
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Could not create ZIP file.');
        }

        foreach ($images as $index => $image) {
            $filePath = Storage::disk('public')->path($image->url);
            if (file_exists($filePath)) {
                $extension = pathinfo($image->url, PATHINFO_EXTENSION);
                $fileName = $project->title . '_image_' . ($index + 1) . '.' . $extension;
                $zip->addFile($filePath, $fileName);
            }
        }

        $zip->close();

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    /**
     * Update the order of images.
     */
    public function updateOrder(Request $request, string $projectId)
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('update', $project);

        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|integer|exists:images,id',
            'orders.*.order' => 'required|integer',
        ]);

        foreach ($validated['orders'] as $orderData) {
            $image = $project->images()->where('id', $orderData['id'])->first();
            if ($image) {
                $this->authorize('update', $image);
                $image->update(['order' => $orderData['order']]);
            }
        }

        return back()->with('success', 'Image order updated successfully!');
    }

    /**
     * Toggle favorite status of an image.
     */
    public function toggleFavorite(string $projectId, string $imageId)
    {
        $image = Image::findOrFail($imageId);
        $this->authorize('update', $image);
        
        $image->update(['is_favorite' => !$image->is_favorite]);

        return back();
    }

    /**
     * Regenerate a new image using the same prompt/format.
     */
    public function regenerate(string $projectId, string $imageId)
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('update', $project);

        $image = $project->images()->whereKey($imageId)->firstOrFail();
        $this->authorize('view', $image);

        $prompt = trim((string) ($image->prompt ?? ''));
        if ($prompt === '') {
            $prompt = (string) GenerationHistory::query()
                ->where('project_id', $project->id)
                ->where('image_id', $image->id)
                ->orderByDesc('id')
                ->value('prompt');
            $prompt = trim($prompt);
        }

        if ($prompt === '') {
            return back()->with('error', 'Unable to regenerate: missing original prompt.');
        }

        $format = (string) data_get($image->metadata, 'format');
        if ($format === '') {
            $format = (string) ($project->format ?? '');
        }
        if ($format === '') {
            $format = 'square';
        }

        $aiModel = app(AIServiceManager::class)->getActiveModelName();

        $generation = $project->generationHistory()->create([
            'user_id' => Auth::id(),
            'prompt' => $prompt,
            'ai_model' => $aiModel,
            'status' => 'pending',
            'parameters' => [
                'format' => $format,
                'source_image_id' => $image->id,
                'action' => 'regenerate',
            ],
        ]);

        $promptItem = [
            'rowIndex'         => -1,
            'title'            => (string) data_get($image->metadata, 'title', ''),
            'generationPrompt' => $prompt,
            'format'           => $format,
            'historyId'        => $generation->id,
        ];

        if (app()->environment('local')) {
            GenerateSingleImageJob::dispatchSync($project->id, 0, $promptItem);
        } else {
            GenerateSingleImageJob::dispatch($project->id, 0, $promptItem);
        }

        app(PostHogService::class)->capture((string) Auth::id(), 'image_regenerated', [
            'project_id' => $project->id,
            'image_id'   => $image->id,
            'format'     => $format,
        ]);

        return back()
            ->with('success', 'Regenerating image...')
            ->with('generating', true);
    }
}
