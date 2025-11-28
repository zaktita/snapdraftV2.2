<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProjectController extends Controller
{
    use AuthorizesRequests;
    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    /**
     * Display a listing of projects.
     */
    public function index(Request $request): Response
    {
        $query = Auth::user()->projects();

        // Apply search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply format filter
        if ($request->has('format') && $request->format) {
            $query->where('format', $request->format);
        }

        // Apply filters
        if ($request->has('filter')) {
            switch ($request->filter) {
                case 'recent':
                    $query->recent();
                    break;
                case 'favorites':
                    $query->favorites();
                    break;
            }
        }

        // Apply sorting (default: favorites first, then most recent)
        $sortBy = $request->get('sort', 'default');
        switch ($sortBy) {
            case 'date-asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'date-desc':
                $query->orderBy('created_at', 'desc');
                break;
            case 'name-asc':
                $query->orderBy('title', 'asc');
                break;
            case 'name-desc':
                $query->orderBy('title', 'desc');
                break;
            case 'images-desc':
                $query->orderBy('images_count', 'desc');
                break;
            default:
                // Default: favorites first, then most recent
                $query->orderBy('is_favorite', 'desc')
                      ->orderBy('created_at', 'desc');
        }

        // Paginate results (20 per page)
        $projects = $query->paginate(20)->through(function ($project) {
            return [
                'id' => $project->id,
                'name' => $project->name,
                'title' => $project->title,
                'format' => $project->format,
                'featured_image' => $project->featured_image ? asset('storage/' . $project->featured_image) : null,
                'created_at' => $project->created_at->toISOString(),
                'updated_at' => $project->updated_at->toISOString(),
                'images_count' => $project->images_count,
                'is_favorite' => $project->is_favorite,
            ];
        });

        return Inertia::render('projects/index', [
            'projects' => $projects,
        ]);
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(): Response
    {
        return Inertia::render('projects/create');
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request)
    {
        $validated = $request->validated();

        // Create the project
        $project = Auth::user()->projects()->create([
            'name' => $validated['name'],
            'title' => $validated['name'], // Use name as title for now
            'description' => $validated['description'] ?? null,
            'settings' => $validated['settings'] ?? null,
            'format' => $validated['format'] ?? null,
        ]);

        // Handle brand reference uploads if present
        if ($request->hasFile('brand_references')) {
            $directory = 'projects/' . $project->id . '/references';
            
            foreach ($request->file('brand_references') as $index => $file) {
                $uploadResult = $this->fileUploadService->uploadImage($file, $directory);
                
                $project->brandReferences()->create([
                    'url' => $uploadResult['url'],
                    'thumbnail_url' => $uploadResult['thumbnail_url'],
                    'order' => $index,
                ]);
            }
        }

        return redirect()->route('projects.show', $project->id)
            ->with('success', 'Project created successfully!');
    }

    /**
     * Display the specified project.
     */
    public function show(Request $request, string $id): Response
    {
        $project = Project::with(['images' => function ($query) {
            $query->orderBy('order')->orderBy('created_at');
        }])->findOrFail($id);

        // Authorize viewing the project
        $this->authorize('view', $project);

        // Check for pending generations
        $hasPendingGenerations = $project->generationHistory()
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        // Calculate progress for active/recent batch (last 5 mins)
        $progress = null;
        if ($hasPendingGenerations) {
            $recentGenerations = $project->generationHistory()
                ->where('created_at', '>=', now()->subMinutes(5))
                ->get();
            
            $progress = [
                'total' => $recentGenerations->count(),
                'completed' => $recentGenerations->where('status', 'completed')->count(),
                'failed' => $recentGenerations->where('status', 'failed')->count(),
                'pending' => $recentGenerations->whereIn('status', ['pending', 'processing'])->count(),
            ];
        }

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'created_at' => $project->created_at->toISOString(),
                'updated_at' => $project->updated_at->toISOString(),
                'images_count' => $project->images_count,
                'is_favorite' => $project->is_favorite,
                'featured_image' => $project->featured_image ? asset('storage/' . $project->featured_image) : null,
                'images' => $project->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => asset('storage/' . $image->url),
                        'thumbnail_url' => $image->thumbnail_url ? asset('storage/' . $image->thumbnail_url) : asset('storage/' . $image->url),
                        'prompt' => $image->prompt,
                        'is_favorite' => $image->is_favorite,
                    ];
                }),
            ],
            // Pass optimistic UI flags from query params
            'justCreated' => $request->query('justCreated', false),
            'expectedImages' => (int) $request->query('expectedImages', 0),
            'hasPendingGenerations' => $hasPendingGenerations,
            'progress' => $progress,
        ]);
    }

    /**
     * Show the form for editing the specified project.
     */
    public function edit(string $id): Response
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        return Inertia::render('projects/edit', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
            ],
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        $validated = $request->validated();

        $project->update($validated);

        return redirect()->back()->with('success', 'Project updated successfully!');
    }

    /**
     * Remove the specified project.
     */
    public function destroy(string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('delete', $project);
        
        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Project deleted successfully!');
    }

    /**
     * Toggle favorite status of a project.
     */
    public function toggleFavorite(string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);
        
        $project->update(['is_favorite' => !$project->is_favorite]);

        return back();
    }

    /**
     * Generate more images for a project.
     * Queues AI generation jobs based on project type.
     */
    public function generateMore(Request $request, string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        // Determine wizard type from project settings
        $wizardType = $project->settings['wizard_type'] ?? null;

        if (!$wizardType) {
            return back()->with('error', 'Unable to determine project type for generation.');
        }

        // Queue appropriate generation job based on wizard type
        switch ($wizardType) {
            case 'csv':
                // For CSV wizard, regenerate all CSV rows
                if (isset($project->settings['csv_data'])) {
                    \App\Jobs\GenerateBatchImagesJob::dispatch($project);
                    return back()->with('success', 'Batch generation started! Images will appear shortly.');
                } else {
                    return back()->with('error', 'CSV data not found for this project.');
                }

            case 'images':
            case 'text':
                // For Images/Text wizard, generate a single new image using project description
                $prompt = $project->description;
                $format = $project->settings['format'] ?? 'square';
                \App\Jobs\GenerateSingleImageJob::dispatch($project, $prompt, $format);
                return back()->with('success', 'Image generation started! Check back soon.');

            default:
                return back()->with('error', 'Unknown project type.');
        }
    }

    /**
     * Get generation progress for a project.
     * Returns statistics about completed/pending generations.
     */
    public function generationProgress(string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        // Get generation history for this project
        $totalGenerations = $project->generationHistory()->count();
        $completedGenerations = $project->generationHistory()
            ->where('status', 'completed')
            ->count();
        $failedGenerations = $project->generationHistory()
            ->where('status', 'failed')
            ->count();
        $processingGenerations = $project->generationHistory()
            ->where('status', 'processing')
            ->count();

        // Calculate expected total from project settings
        $expectedTotal = 0;
        $wizardType = $project->settings['wizard_type'] ?? null;
        
        if ($wizardType === 'csv' && isset($project->settings['csv_rows'])) {
            $expectedTotal = $project->settings['csv_rows'];
        } else {
            $expectedTotal = 1; // Single image for images/text wizard
        }

        return response()->json([
            'project_id' => $project->id,
            'expected_total' => $expectedTotal,
            'completed' => $completedGenerations,
            'failed' => $failedGenerations,
            'processing' => $processingGenerations,
            'total' => $totalGenerations,
            'progress_percentage' => $expectedTotal > 0 
                ? round(($completedGenerations / $expectedTotal) * 100, 2) 
                : 0,
            'is_complete' => $completedGenerations >= $expectedTotal,
        ]);
    }
}
