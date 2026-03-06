<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect to Google OAuth.
     */
    public function redirect()
    {
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

        // 1. Already have a user with this google_id — just log them in
        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user) {
            // Update avatar in case it changed
            $user->update(['avatar' => $googleUser->getAvatar()]);
            Auth::login($user, remember: true);
            return redirect()->intended(route('dashboard'));
        }

        // 2. User exists with same email — link the Google account
        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar'    => $googleUser->getAvatar(),
                // Mark email as verified if it isn't already
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
            Auth::login($user, remember: true);
            return redirect()->intended(route('dashboard'));
        }

        // 3. Brand new user — create account
        $user = User::create([
            'name'              => $googleUser->getName(),
            'email'             => $googleUser->getEmail(),
            'google_id'         => $googleUser->getId(),
            'avatar'            => $googleUser->getAvatar(),
            'email_verified_at' => now(), // Google already verified the email
            'password'          => null,  // No password for OAuth-only users
        ]);

        Auth::login($user, remember: true);

        return redirect()->route('dashboard');
    }
}
