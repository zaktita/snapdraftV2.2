<?php

namespace App\Actions\Fortify;

use App\Models\BetaInvite;
use App\Models\User;
use App\Services\PostHogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'invite_code' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        // is_admin defaults to false in the DB; never mass-assign privilege flags.
        $user = User::create([
            'name'     => $input['name'],
            'email'    => $input['email'],
            'password' => $input['password'],
        ]);

        $signupMethod = 'email';
        $normalizedInviteCode = strtoupper(trim((string) ($input['invite_code'] ?? '')));

        if ($normalizedInviteCode !== '') {
            $invite = BetaInvite::where('code', $normalizedInviteCode)->first();

            if ($invite && $invite->isValid()) {
                try {
                    $invite->redeem($user);
                    $signupMethod = 'invite_code';
                } catch (\Throwable $e) {
                    Log::warning('Optional invite redemption failed during registration', [
                        'email' => $user->email,
                        'code'  => $normalizedInviteCode,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $posthog = app(PostHogService::class);
        $posthog->identify((string) $user->id, [
            'email'      => $user->email,
            'name'       => $user->name,
            'created_at' => $user->created_at,
        ]);
        $posthog->capture((string) $user->id, 'user_signed_up', [
            'signup_method' => $signupMethod,
            'email'         => $user->email,
        ]);

        return $user;
    }
}
