<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BetaApplicationStatus;
use App\Http\Controllers\Controller;
use App\Mail\BetaInviteCodeMail;
use App\Models\BetaApplication;
use App\Services\BetaInviteIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class BetaApplicationAdminController extends Controller
{
    public function index(): Response
    {
        $applications = BetaApplication::query()
            ->with('betaInvite:id,code')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BetaApplication $a) => [
                'id' => $a->id,
                'email' => $a->email,
                'role' => $a->role,
                'monthly_post_volume' => $a->monthly_post_volume,
                'visual_workflow' => $a->visual_workflow,
                'status' => $a->status->value,
                'invite_code' => $a->invite_code,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/beta-applications', [
            'applications' => $applications,
        ]);
    }

    public function approve(BetaApplication $beta_application, BetaInviteIssuer $issuer): RedirectResponse
    {
        if ($beta_application->status !== BetaApplicationStatus::Pending) {
            return back()->with('error', 'This application is not pending.');
        }

        DB::transaction(function () use ($beta_application, $issuer) {
            $invite = $issuer->createInvite();

            $beta_application->update([
                'status' => BetaApplicationStatus::Approved,
                'invite_code' => $invite->code,
                'beta_invite_id' => $invite->id,
            ]);

            $registerUrl = route('register', ['invite' => $invite->code]);

            Mail::to($beta_application->email)->send(
                new BetaInviteCodeMail($invite->code, $registerUrl)
            );
        });

        return back()->with('success', 'Application approved. Invite email sent.');
    }

    public function reject(BetaApplication $beta_application): RedirectResponse
    {
        if ($beta_application->status !== BetaApplicationStatus::Pending) {
            return back()->with('error', 'This application is not pending.');
        }

        $beta_application->update([
            'status' => BetaApplicationStatus::Rejected,
        ]);

        return back()->with('success', 'Application rejected.');
    }
}
