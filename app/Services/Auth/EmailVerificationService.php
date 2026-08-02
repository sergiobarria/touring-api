<?php

namespace App\Services\Auth;

use App\Models\User;
use Throwable;

final readonly class EmailVerificationService
{
    public function send(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
