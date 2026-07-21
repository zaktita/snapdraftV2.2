<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\FeedbackSubmission;
use App\Models\GenerationHistory;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $totalUsers = User::count();
        $adminUsers = User::where('is_admin', true)->count();
        $suspended = User::where('is_suspended', true)->count();
        $activeUsers = User::where('last_generation_at', '>=', now()->subDays(30))->count();
        $totalProjects = Project::count();
        $totalGenerations = GenerationHistory::count();
        $successGen = GenerationHistory::where('status', 'completed')->count();
        $failedGen = GenerationHistory::where('status', 'failed')->count();
        $totalCost = GenerationHistory::sum('cost') ?? 0;
        $activeSubs = Subscription::where('status', 'active')->count();

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
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'created_at' => $u->created_at,
            'is_admin' => $u->is_admin,
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

        $failedJobsCount = 0;
        $pendingJobsCount = 0;
        try {
            $failedJobsCount = DB::table('failed_jobs')->count();
            $pendingJobsCount = DB::table('jobs')->count();
        } catch (\Throwable) {
            // Queue tables may be unavailable on some hosts.
        }

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'admin_users' => $adminUsers,
                'suspended_users' => $suspended,
                'total_projects' => $totalProjects,
                'total_generations' => $totalGenerations,
                'successful_generations' => $successGen,
                'failed_generations' => $failedGen,
                'total_cost' => $totalCost,
                'active_subscriptions' => $activeSubs,
                'total_revenue' => $totalRevenue,
                'revenue_this_month' => $revenueThisMonth,
                'new_users_today' => $newUsersToday,
                'new_users_this_month' => $newUsersMonth,
                'subscription_breakdown' => $subscriptionBreakdown,
                'failed_jobs' => $failedJobsCount,
                'pending_jobs' => $pendingJobsCount,
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
        $filename = 'all-feedback-'.now()->format('Y-m-d-His').'.csv';

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
            'admin' => $query->where('is_admin', true),
            'suspended' => $query->where('is_suspended', true),
            default => null,
        };

        $users = $query->latest()->paginate(25)->through(function ($user) {
            $sub = $user->activeSubscription;
            $caps = $sub?->capabilities ?? [];

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'is_suspended' => (bool) ($user->is_suspended ?? false),
                'suspension_reason' => $user->suspension_reason,
                'has_subscription' => $sub !== null,
                'subscription_id' => $sub?->id,
                'subscription_status' => $sub?->status ?? 'none',
                'subscription_tier' => $sub?->plan?->slug ?? $sub?->plan?->name ?? 'free',
                'subscription_plan' => $sub?->plan?->name ?? null,
                'credits_remaining' => (int) data_get($caps, 'credits_remaining', 0),
                'credits_used' => (int) data_get($caps, 'credits_used', 0),
                'credits_limit' => (int) data_get($caps, 'credits_limit', 0),
                'total_generations' => $user->total_generations ?? 0,
                'created_at' => $user->created_at,
                'last_generation_at' => $user->last_generation_at,
                'email_verified_at' => $user->email_verified_at,
            ];
        });

        return Inertia::render('admin/users', [
            'users' => $users,
            'filters' => $request->only(['search', 'status']),
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'price' => $p->price,
                    'billing_cycle' => $p->billing_cycle,
                    'credits_limit' => data_get($p->capabilities, 'credits_per_month', data_get($p->capabilities, 'credits_limit', 0)),
                    'max_projects' => data_get($p->capabilities, 'max_projects', 0),
                ]),
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'is_admin' => 'sometimes|boolean',
        ]);

        if (array_key_exists('is_admin', $data)) {
            $makeAdmin = (bool) $data['is_admin'];
            unset($data['is_admin']);

            if ($makeAdmin !== (bool) $user->is_admin) {
                if ($user->id === Auth::id()) {
                    return back()->with('error', 'You cannot change your own admin status.');
                }

                if (! $makeAdmin && $this->isLastAdmin($user)) {
                    return back()->with('error', 'Cannot demote the last admin.');
                }

                $user->forceFill(['is_admin' => $makeAdmin]);
                AdminAudit::record($makeAdmin ? 'user.promote_admin' : 'user.demote_admin', $user, [
                    'email' => $user->email,
                ]);
            }
        }

        if ($data !== []) {
            $user->fill($data);
        }

        $user->save();

        return back()->with('success', 'User updated.');
    }

    public function suspendUser(Request $request, User $user)
    {
        if ($redirect = $this->guardCannotModifyPrivilegedUser($user, 'suspend')) {
            return $redirect;
        }

        $reason = $request->input('reason', 'Suspended by admin');

        $user->forceFill([
            'is_suspended' => true,
            'suspension_reason' => $reason,
        ])->save();

        AdminAudit::record('user.suspend', $user, [
            'email' => $user->email,
            'reason' => $reason,
        ]);

        return back()->with('success', 'User suspended.');
    }

    public function reactivateUser(User $user)
    {
        $user->forceFill(['is_suspended' => false, 'suspension_reason' => null])->save();

        AdminAudit::record('user.reactivate', $user, [
            'email' => $user->email,
        ]);

        return back()->with('success', 'User reactivated.');
    }

    public function deleteUser(User $user)
    {
        if ($redirect = $this->guardCannotModifyPrivilegedUser($user, 'delete')) {
            return $redirect;
        }

        if ($this->isLastAdmin($user)) {
            return back()->with('error', 'Cannot delete the last admin.');
        }

        AdminAudit::record('user.delete', $user, [
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    public function sendPasswordReset(User $user)
    {
        Password::sendResetLink(['email' => $user->email]);

        AdminAudit::record('user.password_reset', $user, [
            'email' => $user->email,
        ]);

        return back()->with('success', 'Password reset email sent.');
    }

    public function impersonateUser(User $user)
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot impersonate another admin.');
        }

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Cannot impersonate yourself.');
        }

        AdminAudit::record('user.impersonate', $user, [
            'email' => $user->email,
        ]);

        session(['impersonating_user_id' => Auth::id()]);
        Auth::login($user);
        session()->regenerate();

        return redirect('/dashboard')->with('success', 'Impersonating '.$user->name);
    }

    public function stopImpersonation(Request $request)
    {
        $adminId = session()->pull('impersonating_user_id');
        if ($adminId) {
            $admin = User::find($adminId);
            if ($admin && $admin->is_admin) {
                $impersonatedId = Auth::id();
                Auth::login($admin);
                AdminAudit::record('user.stop_impersonation', null, [
                    'impersonated_user_id' => $impersonatedId,
                ]);
            } else {
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
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description ?? '',
            'price' => $plan->price ?? 0,
            'currency' => $plan->currency ?? 'USD',
            'billing_cycle' => $plan->billing_cycle ?? 'monthly',
            'is_active' => (bool) ($plan->is_active ?? true),
            'is_featured' => (bool) ($plan->is_featured ?? false),
            'has_trial' => (bool) ($plan->has_trial ?? false),
            'trial_days' => (int) ($plan->trial_days ?? 0),
            'sort_order' => (int) ($plan->sort_order ?? 0),
            'provider' => $plan->provider ?? 'lemonsqueezy',
            'provider_product_id' => $plan->provider_product_id ?? null,
            'provider_variant_monthly' => $plan->provider_variant_monthly ?? $plan->lemonsqueezy_variant_id ?? null,
            'provider_variant_yearly' => $plan->provider_variant_yearly ?? null,
            'capabilities' => $plan->capabilities ?? null,
            'subscriptions_count' => $plan->subscriptions_count,
            'deleted_at' => $plan->deleted_at?->toISOString(),
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
            'id' => $sub->id,
            'user' => ['id' => $sub->user?->id, 'name' => $sub->user?->name, 'email' => $sub->user?->email],
            'plan' => ['name' => $sub->plan?->name ?? 'Unknown', 'slug' => $sub->plan?->slug ?? ''],
            'status' => $sub->status,
            'starts_at' => $sub->starts_at,
            'ends_at' => $sub->ends_at,
            'next_billing' => $sub->next_billing_at ?? $sub->renews_at,
            'amount' => $sub->amount_paid ?? $sub->price ?? 0,
            'currency' => $sub->currency ?? 'USD',
        ]);

        return Inertia::render('admin/subscriptions', [
            'subscriptions' => $subs,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function cancelSubscription(Subscription $subscription)
    {
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        AdminAudit::record('subscription.cancel_local', $subscription, [
            'user_id' => $subscription->user_id,
            'note' => 'Local status only — cancel in Lemon Squeezy to stop billing',
        ]);

        return back()->with('success', 'Subscription marked cancelled locally. Cancel in Lemon Squeezy to stop billing.');
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
            $sub = $user->activeSubscription;
            $caps = $sub?->capabilities ?? [];

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'subscription_tier' => $sub?->plan?->slug ?? $sub?->plan?->name ?? 'free',
                'has_subscription' => $sub !== null,
                'credits_remaining' => (int) data_get($caps, 'credits_remaining', 0),
                'credits_used' => (int) data_get($caps, 'credits_used', 0),
                'credits_limit' => (int) data_get($caps, 'credits_limit', 0),
                'total_generations' => $user->total_generations ?? 0,
            ];
        });

        return Inertia::render('admin/credits', [
            'users' => $users,
            'filters' => $request->only(['search']),
        ]);
    }

    public function adjustCredits(Request $request, User $user)
    {
        $request->validate([
            'amount' => 'required|integer|min:1',
            'operation' => 'required|in:add,deduct',
            'reason' => 'nullable|string|max:500',
        ]);

        $sub = $user->subscriptions()->where('status', 'active')->latest()->first();
        if (! $sub) {
            return back()->with('error', 'User has no active subscription.');
        }

        $caps = $sub->capabilities ?? [];
        $current = (int) data_get($caps, 'credits_remaining', 0);
        $delta = (int) $request->input('amount');

        if ($request->input('operation') === 'add') {
            $caps['credits_remaining'] = $current + $delta;
            $caps['credits_limit'] = (int) data_get($caps, 'credits_limit', 0) + $delta;
        } else {
            $caps['credits_remaining'] = max(0, $current - $delta);
        }

        $sub->update(['capabilities' => $caps]);

        AdminAudit::record('credits.adjust', $user, [
            'operation' => $request->input('operation'),
            'amount' => $delta,
            'new_balance' => $caps['credits_remaining'],
            'reason' => $request->input('reason'),
        ]);

        return back()->with('success', 'Credits adjusted.');
    }

    public function projects(Request $request)
    {
        $query = Project::with('user');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $projects = $query->latest()->paginate(25)->through(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'title' => $p->title,
            'format' => $p->format,
            'status' => $p->status ?? 'active',
            'images_count' => (int) ($p->images_count ?? 0),
            'created_at' => $p->created_at,
            'updated_at' => $p->updated_at,
            'user' => ['id' => $p->user?->id, 'name' => $p->user?->name, 'email' => $p->user?->email],
        ]);

        return Inertia::render('admin/projects', [
            'projects' => $projects,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function deleteProject(Project $project)
    {
        AdminAudit::record('project.delete', $project, [
            'owner_id' => $project->user_id,
            'name' => $project->name,
        ]);

        $project->delete();

        return back()->with('success', 'Project deleted.');
    }

    public function analytics(Request $request): Response
    {
        $period = $request->input('period', '30days');
        $days = $this->periodToDays($period);
        $from = now()->subDays($days);

        $signupTrend = User::where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')->orderBy('date')->get()
            ->map(fn ($row) => ['date' => $row->date, 'count' => (int) $row->count])
            ->values();

        $generationTrend = GenerationHistory::where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, COALESCE(SUM(cost), 0) as cost')
            ->groupBy('date')->orderBy('date')->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'count' => (int) $row->count,
                'cost' => (float) $row->cost,
            ])
            ->values();

        $revenueTrend = Transaction::where('status', 'completed')
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, SUM(amount) as revenue')
            ->groupBy('date')->orderBy('date')->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'revenue' => (float) $row->revenue,
            ])
            ->values();

        $topUsers = User::with(['activeSubscription.plan'])
            ->withCount('generationHistory')
            ->orderByDesc('generation_history_count')
            ->take(10)->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'generations' => $u->generation_history_count,
                'subscription_tier' => $u->activeSubscription?->plan?->slug
                    ?? $u->activeSubscription?->plan?->name
                    ?? 'free',
            ]);

        return Inertia::render('admin/analytics', [
            'period' => is_numeric($period) ? $this->daysToPeriodKey((int) $period) : $period,
            'signup_trend' => $signupTrend,
            'generation_trend' => $generationTrend,
            'revenue_trend' => $revenueTrend,
            'top_users' => $topUsers,
            'summary' => [
                'total_revenue' => (float) Transaction::where('status', 'completed')->sum('amount'),
                'revenue_period' => (float) $revenueTrend->sum('revenue'),
                'total_generations' => GenerationHistory::count(),
                'period_generations' => (int) $generationTrend->sum('count'),
                'new_users_period' => (int) $signupTrend->sum('count'),
                'active_subs' => Subscription::where('status', 'active')->count(),
            ],
        ]);
    }

    public function usage(Request $request): Response
    {
        return $this->analytics($request);
    }

    public function updateUserTier(Request $request, User $user)
    {
        $request->validate([
            'plan' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        $plan = Plan::where('slug', $request->input('plan'))->first();

        if (! $plan) {
            return back()->with('error', 'Plan not found.');
        }

        $sub = $user->subscriptions()->where('status', 'active')->latest()->first();

        $planCaps = $plan->capabilities ?? [];
        $credits = (int) data_get($planCaps, 'credits_per_month', data_get($planCaps, 'credits_limit', 0));
        $newCaps = [
            'credits_limit' => $credits,
            'credits_remaining' => $credits,
            'credits_used' => 0,
            'max_projects' => data_get($planCaps, 'max_projects', 0),
        ];

        if ($sub) {
            $sub->update(['plan_id' => $plan->id, 'capabilities' => $newCaps]);
        } else {
            $user->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'billing_period' => $plan->billing_cycle ?? 'monthly',
                'amount_paid' => 0,
                'price' => $plan->price ?? 0,
                'currency' => 'USD',
                'capabilities' => $newCaps,
            ]);
        }

        AdminAudit::record('user.tier_change', $user, [
            'plan' => $plan->slug,
            'plan_name' => $plan->name,
            'reason' => $request->input('reason'),
        ]);

        return back()->with('success', "Plan updated to {$plan->name}.");
    }

    public function failedJobs(Request $request): Response
    {
        $failed = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->paginate(25)
            ->through(function ($job) {
                $payload = json_decode($job->payload, true) ?? [];
                $displayName = $payload['displayName'] ?? ($payload['data']['commandName'] ?? 'Unknown job');
                $exception = (string) $job->exception;
                $firstLine = strtok($exception, "\n") ?: 'Unknown error';

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'connection' => $job->connection,
                    'display_name' => class_basename((string) $displayName),
                    'exception_summary' => mb_substr($firstLine, 0, 240),
                    'failed_at' => $job->failed_at,
                ];
            });

        return Inertia::render('admin/failed-jobs', [
            'jobs' => $failed,
            'stats' => [
                'failed' => DB::table('failed_jobs')->count(),
                'pending' => DB::table('jobs')->count(),
            ],
        ]);
    }

    public function retryFailedJob(string $uuid): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        AdminAudit::record('queue.retry_failed_job', null, ['uuid' => $uuid]);

        return back()->with('success', 'Failed job queued for retry.');
    }

    public function forgetFailedJob(string $uuid): RedirectResponse
    {
        Artisan::call('queue:forget', ['id' => $uuid]);

        AdminAudit::record('queue.forget_failed_job', null, ['uuid' => $uuid]);

        return back()->with('success', 'Failed job discarded.');
    }

    public function retryAllFailedJobs(): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        AdminAudit::record('queue.retry_all_failed_jobs');

        return back()->with('success', 'All failed jobs queued for retry.');
    }

    public function auditLog(Request $request): Response
    {
        $query = AdminAction::with('admin')->latest('created_at');

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhereHas('admin', fn ($aq) => $aq->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"));
            });
        }

        $actions = $query->paginate(40)->through(fn (AdminAction $row) => [
            'id' => $row->id,
            'action' => $row->action,
            'admin' => $row->admin
                ? ['id' => $row->admin->id, 'name' => $row->admin->name, 'email' => $row->admin->email]
                : null,
            'subject_type' => $row->subject_type ? class_basename($row->subject_type) : null,
            'subject_id' => $row->subject_id,
            'metadata' => $row->metadata,
            'ip_address' => $row->ip_address,
            'created_at' => $row->created_at?->toDateTimeString(),
        ]);

        return Inertia::render('admin/audit-log', [
            'actions' => $actions,
            'filters' => $request->only(['search', 'action']),
        ]);
    }

    private function guardCannotModifyPrivilegedUser(User $user, string $verb): ?RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', "You cannot {$verb} your own account.");
        }

        if ($user->isAdmin()) {
            return back()->with('error', "Cannot {$verb} another admin.");
        }

        return null;
    }

    private function isLastAdmin(User $user): bool
    {
        return $user->is_admin && User::where('is_admin', true)->count() <= 1;
    }

    private function periodToDays(string|int $period): int
    {
        return match ((string) $period) {
            '24hours' => 1,
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            default => is_numeric($period) ? max(1, (int) $period) : 30,
        };
    }

    private function daysToPeriodKey(int $days): string
    {
        return match ($days) {
            1 => '24hours',
            7 => '7days',
            90 => '90days',
            default => '30days',
        };
    }
}
