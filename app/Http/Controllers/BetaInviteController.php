<?php

namespace App\Http\Controllers;

use App\Models\BetaInvite;
use App\Services\PostHogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BetaInviteController extends Controller
{
    /**
     * Check whether an invite code is valid (guest-accessible, no redemption).
     */
    public function validateCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $invite = BetaInvite::where('code', strtoupper(trim($request->code)))->first();

        if (!$invite || !$invite->isValid()) {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired invite code.']);
        }

        return response()->json(['valid' => true]);
    }

    /**
     * Redeem a beta invite code for the authenticated user.
     */
    public function redeem(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $user = Auth::user();

        if ($user->hasActiveSubscription()) {
            return back()->with('error', 'You already have an active subscription.');
        }

        $invite = BetaInvite::where('code', strtoupper(trim($request->code)))->first();

        if (!$invite || !$invite->isValid()) {
            return back()->withErrors(['code' => 'Invalid or expired invite code.']);
        }

        // Prevent the same user from redeeming the same code twice
        if ($invite->users()->where('user_id', $user->id)->exists()) {
            return back()->withErrors(['code' => 'Invalid or expired invite code.']);
        }

        try {
            $invite->redeem($user);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        app(PostHogService::class)->capture((string) $user->id, 'beta_invite_redeemed', [
            'credits_granted' => $invite->credits,
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Beta access activated! You have ' . $invite->credits . ' credits for up to 30 days.');
    }
}
