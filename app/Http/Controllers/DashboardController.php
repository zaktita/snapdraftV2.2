<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\GenerationHistory;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        // Get user's projects statistics
        $totalProjects = Project::where('user_id', $user->id)->count();
        $favoriteProjects = Project::where('user_id', $user->id)
            ->where('is_favorite', true)
            ->count();

        // Get recent projects with optimized query (avoid N+1)
        $recentProjects = Project::where('user_id', $user->id)
            ->withCount('images')
            ->with(['images' => function ($query) {
                $query->orderBy('order')->orderBy('created_at')->limit(1);
            }])
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'description' => $project->description,
                    'is_favorite' => $project->is_favorite,
                    'images_count' => $project->images_count, // Use cached count from withCount
                    'thumbnail' => $project->images->first()?->thumbnail_url,
                    'created_at' => $project->created_at->diffForHumans(),
                ];
            });

        // Get generation statistics for current month
        $currentMonth = now()->startOfMonth();
        $generationsThisMonth = GenerationHistory::where('user_id', $user->id)
            ->where('created_at', '>=', $currentMonth)
            ->count();

        $successfulGenerations = GenerationHistory::where('user_id', $user->id)
            ->where('created_at', '>=', $currentMonth)
            ->where('status', 'success')
            ->count();

        $failedGenerations = GenerationHistory::where('user_id', $user->id)
            ->where('created_at', '>=', $currentMonth)
            ->where('status', 'failed')
            ->count();

        // Get total images generated
        $totalImages = DB::table('images')
            ->join('projects', 'images.project_id', '=', 'projects.id')
            ->where('projects.user_id', $user->id)
            ->count();

        // Get credits info
        $creditsRemaining = $user->credits_remaining ?? 0;
        $creditsTotal = $user->credits_total ?? 10;
        $creditsUsed = $creditsTotal - $creditsRemaining;
        $creditsPercentage = $creditsTotal > 0 ? round(($creditsRemaining / $creditsTotal) * 100) : 0;

        // Get subscription info
        $subscriptionTier = $user->subscription_tier ?? 'free';
        $isLowCredits = $creditsPercentage < 20 && $subscriptionTier !== 'enterprise';

        return Inertia::render('dashboard', [
            'stats' => [
                'total_projects' => $totalProjects,
                'favorite_projects' => $favoriteProjects,
                'total_images' => $totalImages,
                'generations_this_month' => $generationsThisMonth,
                'successful_generations' => $successfulGenerations,
                'failed_generations' => $failedGenerations,
                'credits_remaining' => $creditsRemaining,
                'credits_total' => $creditsTotal,
                'credits_used' => $creditsUsed,
                'credits_percentage' => $creditsPercentage,
                'subscription_tier' => $subscriptionTier,
                'is_low_credits' => $isLowCredits,
            ],
            'recent_projects' => $recentProjects,
        ]);
    }
}
