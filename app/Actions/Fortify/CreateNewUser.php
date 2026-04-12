<?php

namespace App\Actions\Fortify;

use App\Models\BetaInvite;
use App\Models\User;
use App\Services\PostHogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'invite_code' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $normalizedInviteCode = strtoupper(trim((string) $input['invite_code']));
        $invite = BetaInvite::where('code', $normalizedInviteCode)->first();

        if (! $invite || ! $invite->isValid()) {
            throw ValidationException::withMessages([
                'invite_code' => 'This invite code is invalid or expired.',
            ]);
        }

        try {
            $user = DB::transaction(function () use ($input, $invite): User {
                $user = User::create([
                    'name'     => $input['name'],
                    'email'    => $input['email'],
                    'password' => $input['password'],
                    'is_admin' => false,
                ]);

                if (! $invite->fresh()?->isValid()) {
                    throw ValidationException::withMessages([
                        'invite_code' => 'This invite code is no longer valid.',
                    ]);
                }

                $invite->fresh()->redeem($user);

                return $user;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Failed to complete invite registration', [
                'email' => $input['email'] ?? null,
                'code'  => $normalizedInviteCode,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'invite_code' => 'Unable to redeem invite code. Please try again.',
            ]);
        }

        $posthog = app(PostHogService::class);
        $posthog->identify((string) $user->id, [
            'email'      => $user->email,
            'name'       => $user->name,
            'created_at' => $user->created_at,
        ]);
        $posthog->capture((string) $user->id, 'user_signed_up', [
            'signup_method' => 'invite_code',
            'email'         => $user->email,
        ]);

        return $user;
    }
}
