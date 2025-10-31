<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects.
     */
    public function index(Request $request): Response
    {
        // TODO: Replace with actual database query
        $projects = [
            [
                'id' => 1,
                'title' => 'Summer Campaign 2025',
                'featured_image' => null,
                'created_at' => '2025-01-15T10:30:00Z',
                'images_count' => 24,
                'is_favorite' => true,
            ],
            [
                'id' => 2,
                'title' => 'Product Launch Assets',
                'featured_image' => null,
                'created_at' => '2025-01-10T14:20:00Z',
                'images_count' => 12,
                'is_favorite' => false,
            ],
            [
                'id' => 3,
                'title' => 'Brand Refresh Materials',
                'featured_image' => null,
                'created_at' => '2024-12-28T09:15:00Z',
                'images_count' => 36,
                'is_favorite' => true,
            ],
        ];

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
    public function store(Request $request)
    {
        // TODO: Implement project creation
        return redirect()->route('projects.index');
    }

    /**
     * Display the specified project.
     */
    public function show(string $id): Response
    {
        // TODO: Fetch project from database
        return Inertia::render('projects/show', [
            'project' => [
                'id' => (int) $id,
                'title' => 'Project ' . $id,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified project.
     */
    public function edit(string $id): Response
    {
        // TODO: Fetch project from database
        return Inertia::render('projects/edit', [
            'project' => [
                'id' => (int) $id,
                'title' => 'Project ' . $id,
            ],
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, string $id)
    {
        // TODO: Implement project update
        return redirect()->route('projects.show', $id);
    }

    /**
     * Remove the specified project.
     */
    public function destroy(string $id)
    {
        // TODO: Implement project deletion
        return redirect()->route('projects.index');
    }

    /**
     * Toggle project favorite status.
     */
    public function toggleFavorite(string $id)
    {
        // TODO: Implement favorite toggle
        return back();
    }
}
