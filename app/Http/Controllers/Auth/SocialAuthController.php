<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BetaInvite;
use App\Models\User;
use App\Services\PostHogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect to Google OAuth.
     */
    public function redirect(Request $request)
    {
        $code = strtoupper(trim((string) $request->query('invite', '')));

        if ($code !== '') {
            $request->session()->put('oauth_invite_code', $code);
        } else {
            $request->session()->forget('oauth_invite_code');
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the Google OAuth callback.
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            Log::error('Google OAuth callback error', ['error' => $e->getMessage()]);
            return redirect()->route('login')->withErrors([
                'email' => 'Google sign-in failed. Please try again.',
            ]);
        }

        // 1. Already have a user with this google_id - just log them in
        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user) {
            // Update avatar in case it changed
            $user->update(['avatar' => $googleUser->getAvatar()]);
            Auth::login($user, remember: true);
            return redirect()->intended(route('dashboard'));
        }

        // 2. User exists with same email - link the Google account
        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar'    => $googleUser->getAvatar(),
                // Mark email as verified if it isn't already
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
            Auth::login($user, remember: true);
            return redirect()->intended(route('dashboard'));
        }

        // 3. Brand new user - create account (invite optional)
        $user = new User([
            'name'     => $googleUser->getName(),
            'email'    => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'avatar'   => $googleUser->getAvatar(),
            'password' => null, // No password for OAuth-only users
        ]);
        $user->forceFill([
            'email_verified_at' => now(), // Google already verified the email
        ])->save();

        $signupMethod = 'google';
        $inviteCode = strtoupper(trim((string) session()->pull('oauth_invite_code', '')));

        if ($inviteCode !== '') {
            $invite = BetaInvite::where('code', $inviteCode)->first();

            if ($invite && $invite->isValid()) {
                try {
                    $invite->redeem($user);
                    $signupMethod = 'google_invite_code';
                } catch (\Throwable $e) {
                    Log::warning('Optional invite redemption failed during Google signup', [
                        'email' => $user->email,
                        'code'  => $inviteCode,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $posthog = app(PostHogService::class);
        $posthog->identify((string) $user->id, [
            'email'         => $user->email,
            'name'          => $user->name,
            'created_at'    => $user->created_at,
        ]);
        $posthog->capture((string) $user->id, 'user_signed_up', [
            'signup_method' => $signupMethod,
            'email'         => $user->email,
        ]);

        Auth::login($user, remember: true);

        return redirect()->route('subscription.plans');
    }
}
