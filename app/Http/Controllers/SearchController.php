<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SearchController extends Controller
{
    /**
     * Show the search page.
     */
    public function index()
    {
        return Inertia::render('search');
    }

    /**
     * Perform a search across projects and images.
     */
    public function search(Request $request)
    {
        $query = $request->validate([
            'q' => 'required|string|min:1|max:255',
        ])['q'];

        $userId = Auth::id();

        // Search projects by name or description
        $projects = Project::where('user_id', $userId)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->withCount('images')
            ->with(['images' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }])
            ->select('id', 'title', 'name', 'description', 'created_at', 'is_favorite')
            ->limit(10)
            ->get()
            ->map(function ($project) {
                // Get the first/latest image as featured image
                $featuredImage = $project->images->first();
                
                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'name' => $project->name,
                    'description' => $project->description,
                    'created_at' => $project->created_at,
                    'is_favorite' => $project->is_favorite,
                    'images_count' => $project->images_count,
                    'featured_image' => $featuredImage ? \Storage::url($featuredImage->url) : null,
                ];
            });

        // Search images by prompt
        $images = Image::whereHas('project', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->where('prompt', 'like', "%{$query}%")
            ->select('id', 'project_id', 'prompt', 'url', 'created_at')
            ->limit(10)
            ->get()
            ->map(function ($image) {
                return [
                    'id' => $image->id,
                    'project_id' => $image->project_id,
                    'prompt' => $image->prompt,
                    'url' => \Storage::url($image->url),
                    'created_at' => $image->created_at,
                ];
            });

        return response()->json([
            'query' => $query,
            'projects' => $projects,
            'images' => $images,
            'total' => $projects->count() + $images->count(),
        ]);
    }
}
