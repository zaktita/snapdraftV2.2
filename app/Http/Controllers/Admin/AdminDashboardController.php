<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Project;
use App\Models\GenerationHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(): Response
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('last_generation_at', '>=', now()->subDays(30))->count(),
            'suspended_users' => User::where('is_suspended', true)->count(),
            'total_projects' => Project::count(),
            'total_generations' => GenerationHistory::count(),
            'successful_generations' => GenerationHistory::where('status', 'completed')->count(),
            'failed_generations' => GenerationHistory::where('status', 'failed')->count(),
            'total_cost' => GenerationHistory::sum('cost'),
            'subscription_breakdown' => User::selectRaw('subscription_tier, count(*) as count')
                ->groupBy('subscription_tier')
                ->get()
                ->pluck('count', 'subscription_tier'),
        ];

        return Inertia::render('admin/dashboard', [
            'stats' => $stats,
        ]);
    }

    /**
     * Display user management page.
     */
    public function users(Request $request): Response
    {
        $query = User::query();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by subscription tier
        if ($request->has('tier') && $request->tier !== 'all') {
            $query->where('subscription_tier', $request->tier);
        }

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'suspended') {
                $query->where('is_suspended', true);
            } elseif ($request->status === 'active') {
                $query->where('is_suspended', false);
            }
        }

        $users = $query->withCount(['projects', 'generationHistory'])
            ->latest()
            ->paginate(50)
            ->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'subscription_tier' => $user->subscription_tier,
                    'credits_remaining' => $user->credits_remaining,
                    'credits_total' => $user->credits_total,
                    'total_generations' => $user->total_generations,
                    'projects_count' => $user->projects_count,
                    'generation_history_count' => $user->generation_history_count,
                    'is_admin' => $user->is_admin,
                    'is_suspended' => $user->is_suspended,
                    'suspension_reason' => $user->suspension_reason,
                    'created_at' => $user->created_at->toDateTimeString(),
                    'last_generation_at' => $user->last_generation_at?->toDateTimeString(),
                ];
            });

        return Inertia::render('admin/users', [
            'users' => $users,
            'filters' => $request->only(['search', 'tier', 'status']),
        ]);
    }

    /**
     * Suspend a user account.
     */
    public function suspendUser(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user = User::findOrFail($id);

        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot suspend an admin user.');
        }

        $user->update([
            'is_suspended' => true,
            'suspension_reason' => $request->reason,
        ]);

        return back()->with('success', 'User suspended successfully.');
    }

    /**
     * Reactivate a suspended user account.
     */
    public function reactivateUser(string $id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'is_suspended' => false,
            'suspension_reason' => null,
        ]);

        return back()->with('success', 'User reactivated successfully.');
    }

    /**
     * Update user subscription tier.
     */
    public function updateUserTier(Request $request, string $id)
    {
        $request->validate([
            'tier' => 'required|in:free,launch,growth,scale',
        ]);

        $user = User::findOrFail($id);
        $user->update(['subscription_tier' => $request->tier]);
        $user->resetMonthlyCredits();

        return back()->with('success', 'User subscription updated successfully.');
    }

    /**
     * Delete a user account.
     */
    public function deleteUser(string $id)
    {
        $user = User::findOrFail($id);

        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot delete an admin user.');
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }

    /**
     * Display usage monitoring page.
     */
    public function usage(Request $request): Response
    {
        $period = $request->get('period', '7days');

        $dateFrom = match($period) {
            '24hours' => now()->subDay(),
            '7days' => now()->subDays(7),
            '30days' => now()->subDays(30),
            '90days' => now()->subDays(90),
            default => now()->subDays(7),
        };

        $usageData = GenerationHistory::where('created_at', '>=', $dateFrom)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(cost) as cost, SUM(tokens_used) as tokens')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topUsers = User::withCount([
            'generationHistory as recent_generations' => function ($query) use ($dateFrom) {
                $query->where('created_at', '>=', $dateFrom);
            }
        ])
        ->having('recent_generations', '>', 0)
        ->orderByDesc('recent_generations')
        ->limit(10)
        ->get()
        ->map(function ($user) {
            return [
                'name' => $user->name,
                'email' => $user->email,
                'generations' => $user->recent_generations,
                'subscription_tier' => $user->subscription_tier,
            ];
        });

        return Inertia::render('admin/usage', [
            'usage_data' => $usageData,
            'top_users' => $topUsers,
            'period' => $period,
        ]);
    }
}
