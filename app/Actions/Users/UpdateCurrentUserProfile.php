<?php

namespace App\Actions\Users;

use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class UpdateCurrentUserProfile
{
    public function __construct(private EmailVerificationService $emailVerification) {}

    /**
     * @throws Throwable
     */
    public function handle(User $user, UpdateProfileRequest $request): User
    {
        $emailChanged = false;

        try {
            $user = DB::transaction(function () use ($user, $request, &$emailChanged): User {
                $user = User::query()->lockForUpdate()->findOrFail($user->getKey());
                $attributes = $request->safe()->only(['name', 'email']);
                $emailChanged = array_key_exists('email', $attributes) && $attributes['email'] !== $user->email;

                if ($emailChanged) {
                    DB::table('password_reset_tokens')
                        ->whereIn('email', [$user->email, $attributes['email']])
                        ->delete();
                    $attributes['email_verified_at'] = null;
                }

                $user->forceFill($attributes)->save();

                return $user;
            });
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23505'], strict: true)) {
                throw ValidationException::withMessages([
                    'email' => __('validation.unique', ['attribute' => 'email']),
                ]);
            }

            throw $exception;
        }

        if ($emailChanged) {
            $this->emailVerification->send($user);
        }

        return $user->load('roles');
    }
}
