<?php

namespace App\Http\Controllers\Admin;

use App\Models\FeedbackSubmission;
use App\Models\GenerationHistory;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $totalUsers        = User::count();
        $adminUsers        = User::where('is_admin', true)->count();
        $suspended         = User::where('is_suspended', true)->count();
        $activeUsers       = User::where('last_generation_at', '>=', now()->subDays(30))->count();
        $totalProjects     = Project::count();
        $totalGenerations  = GenerationHistory::count();
        $successGen        = GenerationHistory::where('status', 'completed')->count();
        $failedGen         = GenerationHistory::where('status', 'failed')->count();
        $totalCost         = GenerationHistory::sum('cost') ?? 0;
        $activeSubs        = Subscription::where('status', 'active')->count();

        $totalRevenue = Transaction::where('status', 'completed')->sum('amount');
        $revenueThisMonth = Transaction::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $newUsersToday = User::whereDate('created_at', today())->count();
        $newUsersMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();

        $subscriptionBreakdown = Subscription::where('subscriptions.status', 'active')
            ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->selectRaw('COALESCE(plans.slug, plans.name, "unknown") as tier, count(*) as count')
            ->groupBy('tier')
            ->get()
            ->pluck('count', 'tier');

        $recentUsers = User::with('activeSubscription.plan')->latest()->take(10)->get()->map(fn ($u) => [
            'id'                => $u->id,
            'name'              => $u->name,
            'email'             => $u->email,
            'created_at'        => $u->created_at,
            'is_admin'          => $u->is_admin,
            'subscription_tier' => $u->activeSubscription?->plan?->slug ?? $u->activeSubscription?->plan?->name ?? 'free',
        ]);

        $feedbackQuery = FeedbackSubmission::query()->with('user');

        if ($dateFrom = $request->input('feedback_date_from')) {
            $feedbackQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('feedback_date_to')) {
            $feedbackQuery->whereDate('created_at', '<=', $dateTo);
        }
        if ($category = $request->input('feedback_category')) {
            $feedbackQuery->where('category', $category);
        }
        if ($ratingMin = $request->input('feedback_rating_min')) {
            $feedbackQuery->where('rating', '>=', (int) $ratingMin);
        }
        if ($ratingMax = $request->input('feedback_rating_max')) {
            $feedbackQuery->where('rating', '<=', (int) $ratingMax);
        }
        if ($email = $request->input('feedback_user_email')) {
            $feedbackQuery->whereHas('user', fn ($q) => $q->where('email', 'like', "%{$email}%"));
        }

        $feedback = $feedbackQuery
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($item) => [
                'id' => $item->id,
                'submitted_at' => optional($item->created_at)->toDateTimeString(),
                'user_id' => $item->user_id,
                'user_name' => $item->user?->name ?? 'Unknown',
                'user_email' => $item->user?->email ?? 'Unknown',
                'rating' => $item->rating,
                'category' => $item->category,
                'message' => $item->message,
            ]);

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'total_users'            => $totalUsers,
                'active_users'           => $activeUsers,
                'admin_users'            => $adminUsers,
                'suspended_users'        => $suspended,
                'total_projects'         => $totalProjects,
                'total_generations'      => $totalGenerations,
                'successful_generations' => $successGen,
                'failed_generations'     => $failedGen,
                'total_cost'             => $totalCost,
                'active_subscriptions'   => $activeSubs,
                'total_revenue'          => $totalRevenue,
                'revenue_this_month'     => $revenueThisMonth,
                'new_users_today'        => $newUsersToday,
                'new_users_this_month'   => $newUsersMonth,
                'subscription_breakdown' => $subscriptionBreakdown,
            ],
            'recent_users' => $recentUsers,
            'feedback' => $feedback,
            'feedback_filters' => $request->only([
                'feedback_date_from',
                'feedback_date_to',
                'feedback_category',
                'feedback_rating_min',
                'feedback_rating_max',
                'feedback_user_email',
            ]),
            'feedback_categories' => ['Bug Report', 'Feature Request', 'General Feedback', 'UX / Design'],
        ]);
    }

    public function downloadFeedback(Request $request)
    {
        $query = FeedbackSubmission::query()->with('user');

        if ($dateFrom = $request->input('feedback_date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('feedback_date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($category = $request->input('feedback_category')) {
            $query->where('category', $category);
        }
        if ($ratingMin = $request->input('feedback_rating_min')) {
            $query->where('rating', '>=', (int) $ratingMin);
        }
        if ($ratingMax = $request->input('feedback_rating_max')) {
            $query->where('rating', '<=', (int) $ratingMax);
        }
        if ($email = $request->input('feedback_user_email')) {
            $query->whereHas('user', fn ($q) => $q->where('email', 'like', "%{$email}%"));
        }

        $rows = $query->latest()->get();
        $filename = 'all-feedback-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Submitted At', 'User ID', 'User Name', 'User Email', 'Rating', 'Category', 'Message']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    optional($row->created_at)->toDateTimeString(),
                    $row->user_id,
                    $row->user?->name ?? 'Unknown',
                    $row->user?->email ?? 'Unknown',
                    $row->rating,
                    $row->category,
                    preg_replace("/\r\n|\r|\n/", ' ', (string) $row->message),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function users(Request $request)
    {
        $query = User::with(['activeSubscription.plan']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        match ($request->input('status')) {
            'admin'     => $query->where('is_admin', true),
            'suspended' => $query->where('is_suspended', true),
            default     => null,
        };

        $users = $query->latest()->paginate(25)->through(function ($user) {
            $sub  = $user->activeSubscription;
            $caps = $sub?->capabilities ?? [];
            return [
                'id'                  => $user->id,
                'name'                => $user->name,
                'email'               => $user->email,
                'is_admin'            => (bool) $user->is_admin,
                'is_suspended'        => (bool) ($user->is_suspended ?? false),
                'suspension_reason'   => $user->suspension_reason,
                'has_subscription'    => $sub !== null,
                'subscription_id'     => $sub?->id,
                'subscription_status' => $sub?->status ?? 'none',
                'subscription_tier'   => $sub?->plan?->slug ?? $sub?->plan?->name ?? 'free',
                'subscription_plan'   => $sub?->plan?->name ?? null,
                'credits_remaining'   => (int) data_get($caps, 'credits_remaining', 0),
                'credits_used'        => (int) data_get($caps, 'credits_used', 0),
                'credits_limit'       => (int) data_get($caps, 'credits_limit', 0),
                'total_generations'   => $user->total_generations ?? 0,
                'created_at'          => $user->created_at,
                'last_generation_at'  => $user->last_generation_at,
                'email_verified_at'   => $user->email_verified_at,
            ];
        });

        return Inertia::render('admin/users', [
            'users'   => $users,
            'filters' => $request->only(['search', 'status']),
            'plans'   => Plan::where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn ($p) => [
                    'id'              => $p->id,
                    'name'            => $p->name,
                    'slug'            => $p->slug,
                    'price'           => $p->price,
                    'billing_cycle'   => $p->billing_cycle,
                    'credits_limit'   => data_get($p->capabilities, 'credits_per_month', data_get($p->capabilities, 'credits_limit', 0)),
                    'max_projects'    => data_get($p->capabilities, 'max_projects', 0),
                ]),
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|unique:users,email,' . $user->id,
            'is_admin' => 'sometimes|boolean',
        ]);

        // Privilege flags must be set explicitly (not via mass assignment fillable).
        if (array_key_exists('is_admin', $data)) {
            $user->forceFill(['is_admin' => (bool) $data['is_admin']]);
            unset($data['is_admin']);
        }

        if ($data !== []) {
            $user->update($data);
        } else {
            $user->save();
        }

        return back()->with('success', 'User updated.');
    }

    public function suspendUser(Request $request, User $user)
    {
        $user->forceFill([
            'is_suspended'      => true,
            'suspension_reason' => $request->input('reason', 'Suspended by admin'),
        ])->save();
        Log::info('[admin] User suspended', [
            'admin_id'   => Auth::id(),
            'target_id'  => $user->id,
            'target_email' => $user->email,
            'reason'     => $request->input('reason', 'Suspended by admin'),
        ]);
        return back()->with('success', 'User suspended.');
    }

    public function reactivateUser(User $user)
    {
        $user->forceFill(['is_suspended' => false, 'suspension_reason' => null])->save();
        Log::info('[admin] User reactivated', [
            'admin_id'     => Auth::id(),
            'target_id'    => $user->id,
            'target_email' => $user->email,
        ]);
        return back()->with('success', 'User reactivated.');
    }

    public function deleteUser(User $user)
    {
        Log::warning('[admin] User deleted', [
            'admin_id'     => Auth::id(),
            'target_id'    => $user->id,
            'target_email' => $user->email,
        ]);
        $user->delete();
        return back()->with('success', 'User deleted.');
    }

    public function sendPasswordReset(User $user)
    {
        Password::sendResetLink(['email' => $user->email]);
        return back()->with('success', 'Password reset email sent.');
    }

    public function impersonateUser(User $user)
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot impersonate another admin.');
        }

        Log::warning('[admin] Impersonation started', [
            'admin_id'     => Auth::id(),
            'target_id'    => $user->id,
            'target_email' => $user->email,
        ]);
        session(['impersonating_user_id' => Auth::id()]);
        Auth::login($user);
        session()->regenerate();

        return redirect('/dashboard')->with('success', 'Impersonating ' . $user->name);
    }

    public function stopImpersonation(Request $request)
    {
        $adminId = session()->pull('impersonating_user_id');
        if ($adminId) {
            $admin = User::find($adminId);
            if ($admin && $admin->is_admin) {
                Log::info('[admin] Impersonation ended', [
                    'admin_id'          => $adminId,
                    'impersonated_user' => Auth::id(),
                ]);
                Auth::login($admin);
            } else {
                Log::warning('[admin] Invalid impersonation stop attempt', [
                    'claimed_admin_id'  => $adminId,
                    'impersonated_user' => Auth::id(),
                ]);
                Auth::logout();
                $request->session()->invalidate();
                return redirect('/login');
            }
        }
        return redirect('/admin')->with('success', 'Stopped impersonation.');
    }

    public function plans()
    {
        $plans = Plan::withCount(['subscriptions' => function ($q) {
            $q->where('status', 'active');
        }])->withTrashed()->get()->map(fn ($plan) => [
            'id'                       => $plan->id,
            'name'                     => $plan->name,
            'slug'                     => $plan->slug,
            'description'              => $plan->description ?? '',
            'price'                    => $plan->price ?? 0,
            'currency'                 => $plan->currency ?? 'USD',
            'billing_cycle'            => $plan->billing_cycle ?? 'monthly',
            'is_active'                => (bool) ($plan->is_active ?? true),
            'is_featured'              => (bool) ($plan->is_featured ?? false),
            'has_trial'                => (bool) ($plan->has_trial ?? false),
            'trial_days'               => (int) ($plan->trial_days ?? 0),
            'sort_order'               => (int) ($plan->sort_order ?? 0),
            'provider'                 => $plan->provider ?? 'lemonsqueezy',
            'provider_product_id'      => $plan->provider_product_id ?? null,
            'provider_variant_monthly' => $plan->provider_variant_monthly ?? $plan->lemonsqueezy_variant_id ?? null,
            'provider_variant_yearly'  => $plan->provider_variant_yearly ?? null,
            'capabilities'             => $plan->capabilities ?? null,
            'subscriptions_count'      => $plan->subscriptions_count,
            'deleted_at'               => $plan->deleted_at?->toISOString(),
        ]);

        return Inertia::render('admin/plans', ['plans' => $plans]);
    }

    public function subscriptions(Request $request)
    {
        $query = Subscription::with(['user', 'plan']);

        if ($search = $request->input('search')) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $subs = $query->latest()->paginate(25)->through(fn ($sub) => [
            'id'           => $sub->id,
            'user'         => ['id' => $sub->user?->id, 'name' => $sub->user?->name, 'email' => $sub->user?->email],
            'plan'         => ['name' => $sub->plan?->name ?? 'Unknown', 'slug' => $sub->plan?->slug ?? ''],
            'status'       => $sub->status,
            'starts_at'    => $sub->starts_at,
            'ends_at'      => $sub->ends_at,
            'next_billing' => $sub->next_billing_at ?? $sub->renews_at,
            'amount'       => $sub->amount_paid ?? $sub->price ?? 0,
            'currency'     => $sub->currency ?? 'USD',
        ]);

        return Inertia::render('admin/subscriptions', [
            'subscriptions' => $subs,
            'filters'       => $request->only(['search', 'status']),
        ]);
    }

    public function cancelSubscription(Subscription $subscription)
    {
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        return back()->with('success', 'Subscription cancelled.');
    }

    public function credits(Request $request)
    {
        $query = User::with('activeSubscription.plan');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(25)->through(function ($user) {
            $sub  = $user->activeSubscription;
            $caps = $sub?->capabilities ?? [];
            return [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'subscription_tier' => $sub?->plan?->slug ?? $sub?->plan?->name ?? 'free',
                'has_subscription'  => $sub !== null,
                'credits_remaining' => (int) data_get($caps, 'credits_remaining', 0),
                'credits_used'      => (int) data_get($caps, 'credits_used', 0),
                'credits_limit'     => (int) data_get($caps, 'credits_limit', 0),
                'total_generations' => $user->total_generations ?? 0,
            ];
        });

        return Inertia::render('admin/credits', [
            'users'   => $users,
            'filters' => $request->only(['search']),
        ]);
    }

    public function adjustCredits(Request $request, User $user)
    {
        $request->validate([
            'amount'    => 'required|integer|min:1',
            'operation' => 'required|in:add,deduct',
        ]);

        $sub = $user->subscriptions()->where('status', 'active')->latest()->first();
        if (!$sub) {
            return back()->with('error', 'User has no active subscription.');
        }

        $caps    = $sub->capabilities ?? [];
        $current = (int) data_get($caps, 'credits_remaining', 0);
        $delta   = (int) $request->input('amount');

        if ($request->input('operation') === 'add') {
            $caps['credits_remaining'] = $current + $delta;
            $caps['credits_limit']     = (int) data_get($caps, 'credits_limit', 0) + $delta;
        } else {
            $caps['credits_remaining'] = max(0, $current - $delta);
        }

        $sub->update(['capabilities' => $caps]);
        Log::info('[admin] Credits adjusted', [
            'admin_id'   => Auth::id(),
            'target_id'  => $user->id,
            'operation'  => $request->input('operation'),
            'amount'     => $delta,
            'new_balance' => $caps['credits_remaining'],
        ]);
        return back()->with('success', 'Credits adjusted.');
    }

    public function projects(Request $request)
    {
        $query = Project::with('user');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $projects = $query->latest()->paginate(25)->through(fn ($p) => [
            'id'          => $p->id,
            'name'        => $p->name,
            'user'        => ['id' => $p->user?->id, 'name' => $p->user?->name, 'email' => $p->user?->email],
            'status'      => $p->status ?? 'active',
            'generations' => $p->generation_count ?? 0,
            'created_at'  => $p->created_at,
            'updated_at'  => $p->updated_at,
        ]);

        return Inertia::render('admin/projects', [
            'projects' => $projects,
            'filters'  => $request->only(['search', 'status']),
        ]);
    }

    public function deleteProject(Project $project)
    {
        Log::warning('[admin] Project deleted', [
            'admin_id'   => Auth::id(),
            'project_id' => $project->id,
            'owner_id'   => $project->user_id,
        ]);
        $project->delete();
        return back()->with('success', 'Project deleted.');
    }

    public function analytics(Request $request)
    {
        $period = $request->input('period', '30');
        $days   = (int) $period;
        $from   = now()->subDays($days);

        $signups = User::where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')->orderBy('date')->get();

        $generations = GenerationHistory::where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')->orderBy('date')->get();

        $revenue = Transaction::where('status', 'completed')
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, SUM(amount) as amount')
            ->groupBy('date')->orderBy('date')->get();

        $topUsers = User::withCount('generationHistory')
            ->orderByDesc('generation_history_count')
            ->take(10)->get()
            ->map(fn ($u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'generations' => $u->generation_history_count,
            ]);

        return Inertia::render('admin/analytics', [
            'period'      => $period,
            'signups'     => $signups,
            'generations' => $generations,
            'revenue'     => $revenue,
            'top_users'   => $topUsers,
            'totals'      => [
                'signups'     => $signups->sum('count'),
                'generations' => $generations->sum('count'),
                'revenue'     => $revenue->sum('amount'),
            ],
        ]);
    }

    public function usage(Request $request)
    {
        return $this->analytics($request);
    }

    public function updateUserTier(Request $request, User $user)
    {
        $request->validate(['plan' => 'required|string']);

        $plan = Plan::where('slug', $request->input('plan'))->first();

        if (!$plan) {
            return back()->with('error', 'Plan not found.');
        }

        $sub = $user->subscriptions()->where('status', 'active')->latest()->first();

        $planCaps  = $plan->capabilities ?? [];
        $credits   = (int) data_get($planCaps, 'credits_per_month', data_get($planCaps, 'credits_limit', 0));
        $newCaps   = [
            'credits_limit'     => $credits,
            'credits_remaining' => $credits,
            'credits_used'      => 0,
            'max_projects'      => data_get($planCaps, 'max_projects', 0),
        ];

        if ($sub) {
            $sub->update(['plan_id' => $plan->id, 'capabilities' => $newCaps]);
        } else {
            $user->subscriptions()->create([
                'plan_id'        => $plan->id,
                'status'         => 'active',
                'starts_at'      => now(),
                'billing_period' => $plan->billing_cycle ?? 'monthly',
                'amount_paid'    => 0,
                'price'          => $plan->price ?? 0,
                'currency'       => 'USD',
                'capabilities'   => $newCaps,
            ]);
        }

        return back()->with('success', "Plan updated to {$plan->name}.");
    }
}
