<?php

namespace App\Http\Controllers;

use App\Enums\BetaApplicationStatus;
use App\Models\BetaApplication;
use App\Notifications\NewBetaApplicationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BetaApplicationController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('website.beta-apply');
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $roles = config('beta_application.roles', []);
        $volumes = config('beta_application.monthly_post_volumes', []);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'max:64', Rule::in($roles)],
            'monthly_post_volume' => ['required', 'string', 'max:32', Rule::in($volumes)],
            'visual_workflow' => ['required', 'string', 'max:5000'],
        ]);

        $email = strtolower(trim($validated['email']));

        $duplicatePending = BetaApplication::query()
            ->where('email', $email)
            ->where('status', BetaApplicationStatus::Pending)
            ->exists();

        if ($duplicatePending) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending application with this email.',
                    'errors' => ['email' => ['You already have a pending application with this email.']],
                ], 422);
            }

            return back()
                ->withErrors(['email' => 'You already have a pending application with this email.'])
                ->withInput();
        }

        $application = BetaApplication::create([
            'email' => $email,
            'role' => $validated['role'],
            'monthly_post_volume' => $validated['monthly_post_volume'],
            'visual_workflow' => $validated['visual_workflow'],
            'status' => BetaApplicationStatus::Pending,
        ]);

        $notifyTo = trim((string) config('snapdraft.beta_application_notify_email', ''));

        if ($notifyTo !== '') {
            Notification::route('mail', $notifyTo)
                ->notify(new NewBetaApplicationNotification($application));
        } else {
            Log::warning('Beta application received but BETA_APPLICATION_NOTIFY_EMAIL is not set.', [
                'application_id' => $application->id,
                'email' => $application->email,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('beta.apply.form')
            ->with('beta_applied', true)
            ->with('beta_email', $email);
    }
}
