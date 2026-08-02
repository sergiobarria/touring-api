<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

final readonly class VerifyUserEmail
{
    /**
     * @throws AuthorizationException|ModelNotFoundException
     */
    public function handle(string $userId, string $hash): void
    {
        $user = User::query()->findOrFail($userId);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new AuthorizationException;
        }

        if (! $user->hasVerifiedEmail() && ! $user->markEmailAsVerified()) {
            throw new RuntimeException('The email address could not be marked as verified.');
        }
    }
}
