<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\GenerationHistory;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
                $query->latest()->limit(1);
            }])
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($project) {
                $latestImage = $project->images->first();
                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'description' => $project->description,
                    'is_favorite' => $project->is_favorite,
                    'images_count' => $project->images_count, // Use cached count from withCount
                    'thumbnail' => $latestImage?->thumbnail_url,
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
        $creditsRemaining = $user->creditsRemaining();
        $creditsTotal = $user->creditsTotal();
        $creditsUsed = max(0, $creditsTotal - $creditsRemaining);
        $creditsPercentage = $creditsTotal > 0 ? min(100, round(($creditsUsed / $creditsTotal) * 100)) : 0;
        // Get subscription info
        $subscriptionTier = $user->currentTier() ?? 'free';
        $isLowCredits = $creditsTotal > 0 && ($creditsRemaining / $creditsTotal) < 0.20; // less than 20% remaining

        // Get tier limits
        $tierLimits = SubscriptionService::getTierLimits($subscriptionTier);
        $remainingProjectSlots = SubscriptionService::getRemainingProjectSlots($user);

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
                'max_projects' => $tierLimits['max_projects'],
                'remaining_project_slots' => $remainingProjectSlots,
                'csv_max_rows' => $tierLimits['csv_max_rows'],
            ],
            'recent_projects' => $recentProjects,
        ]);
    }
}
